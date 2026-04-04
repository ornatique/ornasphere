<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\ReturnCart;
use App\Models\ApprovalHeader;
use App\Models\ApprovalItem;
use Illuminate\Support\Facades\Schema;

class SaleReturnApiController extends Controller
{
    public function list_of_return(Request $request)
    {
        $user = auth()->user();

        $returns = SaleReturn::with([
                'sale.customer',
                'approval.customer',
                'items.saleItem.sale.customer',
            ])
            ->where('company_id', $user->company_id)
            ->latest()
            ->get()
            ->map(function ($return) {
                $customerName = '-';

                if ($return->sale && $return->sale->customer) {
                    $customerName = $return->sale->customer->name;
                } elseif ($return->approval && $return->approval->customer) {
                    $customerName = $return->approval->customer->name;
                } else {
                    $firstSaleItem = $return->items->firstWhere('sale_item_id', '!=', null);
                    $customerName = optional(optional(optional($firstSaleItem)->saleItem)->sale->customer)->name ?? '-';
                }

                return [
                    'id' => $return->id,
                    'voucher_no' => $return->return_voucher_no,
                    'return_date' => $return->return_date,
                    'customer_name' => $customerName,
                    'return_total' => (float) $return->return_total,
                    'source_type' => $return->source_type,
                    'source_id' => $return->source_id,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $returns
        ]);
    }

    public function getSalesForReturn(Request $request)
    {
        $user = auth()->user();

        $sales = Sale::with(['customer', 'saleItems.itemset'])
            ->where('company_id', $user->company_id);

        if ($request->customer_id) {
            $sales->where('customer_id', $request->customer_id);
        }

        if ($request->from_date) {
            $sales->whereDate('sale_date', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $sales->whereDate('sale_date', '<=', $request->to_date);
        }

        if ($request->item_id) {
            $sales->whereHas('saleItems.itemset', function ($q) use ($request) {
                $q->where('item_id', $request->item_id);
            });
        }

        $rows = $sales->orderByDesc('id')->get()->map(function ($sale) {
            return [
                'sale_id' => $sale->id,
                'voucher_no' => $sale->voucher_no,
                'customer_name' => optional($sale->customer)->name,
                'sale_date' => optional($sale->sale_date)->format('Y-m-d') ?? null,
                'net_total' => (float) $sale->net_total,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $rows
        ]);
    }

    public function saleDetails($saleId)
    {
        $user = auth()->user();

        $sale = Sale::with('saleItems.itemset.item', 'customer')
            ->where('company_id', $user->company_id)
            ->findOrFail($saleId);

        return response()->json([
            'success' => true,
            'data' => $sale
        ]);
    }

    public function scanProduct(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'qr_code' => 'required|string',
            'customer_id' => 'required|integer',
        ]);

        $qr = $request->qr_code;
        $customerId = (int) $request->customer_id;

        $saleItem = SaleItem::whereHas('itemset', function ($q) use ($qr, $user) {
                $q->where('company_id', $user->company_id)
                    ->where('qr_code', $qr);
            })
            ->whereHas('sale', function ($q) use ($customerId, $user) {
                $q->where('company_id', $user->company_id)
                    ->where('customer_id', $customerId);
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('sale_return_items')
                    ->whereColumn('sale_return_items.sale_item_id', 'sale_items.id');
            })
            ->with('itemset.item')
            ->first();

        if (!$saleItem) {
            return response()->json([
                'success' => false,
                'message' => 'Product not sold to this customer or already returned'
            ]);
        }

        $exists = ReturnCart::where('sale_item_id', $saleItem->id)
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Product already scanned'
            ]);
        }

        ReturnCart::create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'sale_item_id' => $saleItem->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Added to return list'
        ]);
    }

    // Exact scanner endpoint for app (scan first, store later)
    public function scanQr(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'qr_code' => 'required|string',
            'customer_id' => 'required|integer',
        ]);

        $saleItem = SaleItem::whereHas('itemset', function ($q) use ($request, $user) {
                $q->where('company_id', $user->company_id)
                    ->where('qr_code', trim((string) $request->qr_code));
            })
            ->whereHas('sale', function ($q) use ($request, $user) {
                $q->where('company_id', $user->company_id)
                    ->where('customer_id', (int) $request->customer_id);
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('sale_return_items')
                    ->whereColumn('sale_return_items.sale_item_id', 'sale_items.id');
            })
            ->with('itemset.item', 'sale.customer')
            ->first();

        if (!$saleItem) {
            return response()->json([
                'success' => false,
                'message' => 'Product not sold to this customer or already returned'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sale_item_id' => $saleItem->id,
                'sale_id' => $saleItem->sale_id,
                'voucher_no' => optional($saleItem->sale)->voucher_no,
                'customer' => optional(optional($saleItem->sale)->customer)->name,
                'item_id' => optional($saleItem->itemset)->item_id,
                'huid' => optional($saleItem->itemset)->HUID,
                'qr_code' => optional($saleItem->itemset)->qr_code,
                'item_name' => optional(optional($saleItem->itemset)->item)->item_name,
                'gross_weight' => (float) ($saleItem->gross_weight ?? 0),
                'other_weight' => (float) ($saleItem->other_weight ?? 0),
                'net_weight' => (float) ($saleItem->net_weight ?? 0),
                'purity' => (float) ($saleItem->purity ?? 0),
                'waste_percent' => (float) ($saleItem->waste_percent ?? 0),
                'net_purity' => (float) ($saleItem->net_purity ?? 0),
                'fine_weight' => (float) ($saleItem->fine_weight ?? 0),
                'metal_rate' => (float) ($saleItem->metal_rate ?? 0),
                'metal_amount' => (float) ($saleItem->metal_amount ?? 0),
                'labour_rate' => (float) ($saleItem->labour_rate ?? 0),
                'labour_amount' => (float) ($saleItem->labour_amount ?? 0),
                'other_amount' => (float) ($saleItem->other_amount ?? 0),
                'total_amount' => (float) ($saleItem->total_amount ?? 0),
                'source' => 'sale',
            ]
        ]);
    }

    public function returnCartList()
    {
        $user = auth()->user();

        $items = ReturnCart::with('saleItem.itemset.item')
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->latest()
            ->get()
            ->map(function ($cart) {
                $saleItem = $cart->saleItem;
                if (!$saleItem || !$saleItem->itemset) {
                    return null;
                }

                return [
                    'cart_id' => $cart->id,
                    'sale_item_id' => $saleItem->id,
                    'serial_no' => $saleItem->itemset->serial_no,
                    'qr_code' => $saleItem->itemset->qr_code,
                    'gross_weight' => $saleItem->itemset->gross_weight,
                    'net_weight' => $saleItem->itemset->net_weight,
                    'item_name' => optional($saleItem->itemset->item)->item_name,
                    'purity' => optional($saleItem->itemset->item)->purity,
                    'amount' => (float) $saleItem->total_amount
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'data' => $items
        ]);
    }

    public function removeCartItem($id)
    {
        $user = auth()->user();

        $cart = ReturnCart::where('id', $id)
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found in return list'
            ]);
        }

        $cart->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from return list'
        ]);
    }

    public function confirmReturn(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'sale_id' => 'required|integer',
        ]);

        $cartItems = ReturnCart::with('saleItem.itemset')
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No items scanned for return'
            ]);
        }

        DB::beginTransaction();

        try {
            $saleId = (int) $request->sale_id;

            $sale = Sale::where('company_id', $user->company_id)->findOrFail($saleId);

            $return = SaleReturn::create([
                'company_id' => $user->company_id,
                'sale_id' => $saleId,
                'return_voucher_no' => 'SR' . time(),
                'return_date' => now(),
                'return_total' => 0
            ]);

            $total = 0;

            foreach ($cartItems as $cart) {
                $saleItem = $cart->saleItem;

                if (!$saleItem || !$saleItem->itemset) {
                    continue;
                }

                SaleReturnItem::create([
                    'sale_return_id' => $return->id,
                    'sale_item_id' => $saleItem->id,
                    'return_amount' => $saleItem->total_amount
                ]);

                $saleItem->itemset->update([
                    'is_sold' => 0
                ]);

                $total += (float) $saleItem->total_amount;
            }

            $return->update([
                'return_total' => $total
            ]);

            $sale->decrement('net_total', $total);

            ReturnCart::where('user_id', $user->id)
                ->where('company_id', $user->company_id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Return completed successfully',
                'return_id' => $return->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'sale_id' => 'required|integer',
            'return_items' => 'required|array|min:1',
            'return_items.*' => 'required|integer',
        ]);

        DB::beginTransaction();

        try {
            $sale = Sale::where('company_id', $user->company_id)
                ->findOrFail($request->sale_id);

            $return = SaleReturn::create([
                'company_id' => $user->company_id,
                'sale_id' => $sale->id,
                'return_voucher_no' => 'SR' . time(),
                'return_date' => now(),
                'return_total' => 0
            ]);

            $total = 0;

            foreach ($request->return_items as $saleItemId) {
                $saleItem = SaleItem::whereHas('sale', function ($q) use ($user, $sale) {
                        $q->where('company_id', $user->company_id)
                            ->where('id', $sale->id);
                    })
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('sale_return_items')
                            ->whereColumn('sale_return_items.sale_item_id', 'sale_items.id');
                    })
                    ->findOrFail($saleItemId);

                SaleReturnItem::create([
                    'sale_return_id' => $return->id,
                    'sale_item_id' => $saleItem->id,
                    'return_amount' => $saleItem->total_amount
                ]);

                $saleItem->itemset->update([
                    'is_sold' => 0
                ]);

                $total += (float) $saleItem->total_amount;
            }

            $return->update([
                'return_total' => $total
            ]);

            $sale->decrement('net_total', $total);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale return created successfully',
                'data' => ['return_id' => $return->id]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function pdf($returnId)
    {
        $user = auth()->user();

        $return = SaleReturn::with([
                'sale.customer',
                'approval.customer',
                'approval.items.itemSet.item',
                'items.saleItem.itemset.item',
                'items.itemSet.item',
            ])
            ->where('company_id', $user->company_id)
            ->findOrFail($returnId);

        $pdf = Pdf::loadView('company.returns.return_pdf', compact('return'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('Return-' . $return->return_voucher_no . '.pdf');
    }

    // Add Label Return Approval (same as web button flow)
    public function approvalReturnItems(Request $request)
    {
        $companyId = $request->user()->company_id;
        $customerId = (int) $request->input('customer_id', 0);

        if ($customerId <= 0) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $rows = ApprovalItem::with('itemSet.item', 'legacyItemSet.item')
            ->whereHas('approval', function ($q) use ($companyId, $customerId) {
                $q->where('company_id', $companyId)
                    ->where('customer_id', $customerId);
            })
            ->where('status', 'pending')
            ->get()
            ->map(function ($row) {
                $itemSet = $row->itemSet ?? $row->legacyItemSet;
                $item = optional($itemSet)->item;
                $gross = (float) ($row->gross_weight ?? 0);
                $otherWeight = (float) ($row->other_weight ?? 0);
                $net = (float) ($row->net_weight ?? ($gross - $otherWeight));
                $purity = (float) ($row->purity ?? optional($item)->outward_purity ?? 0);
                $wastePercent = (float) ($row->waste_percent ?? 0);
                $netPurity = (float) ($row->net_purity ?? ($purity - $wastePercent));
                $fineWeight = (float) ($row->total_fine_weight ?? (($net * $netPurity) / 100));
                $metalRate = (float) ($row->metal_rate ?? 0);
                $metalAmount = (float) ($row->metal_amount ?? ($net * $metalRate));
                $labourRate = (float) ($row->labour_rate ?? optional($itemSet)->sale_labour_rate ?? optional($item)->labour_rate ?? 0);
                $labourAmount = (float) ($row->labour_amount ?? ($net * $labourRate));
                $otherAmount = (float) ($row->other_amount ?? optional($itemSet)->sale_other ?? 0);
                $totalAmount = (float) ($row->total_amount ?? ($metalAmount + $labourAmount + $otherAmount));

                return [
                    'source' => 'approval',
                    'approval_item_id' => $row->id,
                    'approval_id' => $row->approval_id,
                    'item_id' => $row->item_id ?? optional($itemSet)->item_id,
                    'itemset_id' => optional($itemSet)->id,
                    'qr_code' => $row->qr_code ?? optional($itemSet)->qr_code,
                    'huid' => $row->huid ?? optional($itemSet)->HUID,
                    'name' => optional($item)->item_name,
                    'gross_weight' => $gross,
                    'other_weight' => $otherWeight,
                    'net_weight' => $net,
                    'purity' => $purity,
                    'waste_percent' => $wastePercent,
                    'net_purity' => $netPurity,
                    'fine_weight' => $fineWeight,
                    'metal_rate' => $metalRate,
                    'metal_amount' => $metalAmount,
                    'labour_rate' => $labourRate,
                    'labour_amount' => $labourAmount,
                    'other_amount' => $otherAmount,
                    'total_amount' => $totalAmount,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows
        ]);
    }

    // Process mixed return: sale items + approval items in one voucher
    public function processSelected(Request $request)
    {
        $companyId = $request->user()->company_id;

        $saleItemIds = collect($request->input('sale_item_ids', []))
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $approvalItemIds = collect($request->input('approval_item_ids', []))
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $rowPayloads = collect($request->input('row_payloads', []))
            ->map(function ($payload) {
                if (is_array($payload)) {
                    return $payload;
                }
                $decoded = json_decode((string) $payload, true);
                return is_array($decoded) ? $decoded : null;
            })
            ->filter()
            ->values();

        $payloadMap = [];
        foreach ($rowPayloads as $payload) {
            $rowType = (string) ($payload['type'] ?? '');
            $rowId = (int) ($payload['id'] ?? 0);
            if (!$rowType || !$rowId) {
                continue;
            }
            $payloadMap["{$rowType}_{$rowId}"] = [
                'total_amount' => (float) ($payload['total_amount'] ?? 0),
            ];
        }

        if ($saleItemIds->isEmpty() && $approvalItemIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one item for return.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $hasItemsetIdColumn = Schema::hasColumn('sale_return_items', 'itemset_id');
            $hasProductIdColumn = Schema::hasColumn('sale_return_items', 'product_id');
            $total = 0;

            $saleItems = collect();
            if ($saleItemIds->isNotEmpty()) {
                $saleItems = SaleItem::with(['sale', 'itemset'])
                    ->whereIn('id', $saleItemIds)
                    ->whereHas('sale', function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })
                    ->get()
                    ->filter(function ($saleItem) {
                        return !SaleReturnItem::where('sale_item_id', $saleItem->id)->exists();
                    })
                    ->values();
            }

            $approvalItems = collect();
            if ($approvalItemIds->isNotEmpty()) {
                $approvalItems = ApprovalItem::with(['approval', 'itemSet'])
                    ->whereIn('id', $approvalItemIds)
                    ->where('status', 'pending')
                    ->whereHas('approval', function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })
                    ->get()
                    ->values();
            }

            if ($saleItems->isEmpty() && $approvalItems->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No valid items found for return.'
                ], 422);
            }

            $saleIds = $saleItems->pluck('sale_id')->unique()->values();
            $approvalIds = $approvalItems->pluck('approval_id')->unique()->values();

            $return = SaleReturn::create([
                'company_id' => $companyId,
                'sale_id' => $saleIds->count() === 1 ? $saleIds->first() : null,
                'source_type' => $approvalIds->isNotEmpty() ? ($saleIds->isNotEmpty() ? 'mixed' : 'approval') : 'sale',
                'source_id' => $approvalIds->count() === 1 ? $approvalIds->first() : null,
                'return_voucher_no' => 'SR' . now()->format('YmdHis') . rand(10, 99),
                'return_date' => now(),
                'return_total' => 0,
            ]);

            if ($saleItems->isNotEmpty()) {
                $saleTotalsBySaleId = [];
                foreach ($saleItems as $saleItem) {
                    $rowKey = 'sale_' . (int) $saleItem->id;
                    $overrideAmount = isset($payloadMap[$rowKey]['total_amount'])
                        ? (float) $payloadMap[$rowKey]['total_amount']
                        : (float) $saleItem->total_amount;

                    SaleReturnItem::create([
                        'sale_return_id' => $return->id,
                        'sale_item_id' => $saleItem->id,
                        'return_amount' => $overrideAmount,
                    ]);

                    if ($saleItem->itemset) {
                        $saleItem->itemset->update(['is_sold' => 0]);
                    }

                    $total += $overrideAmount;
                    $saleId = (int) $saleItem->sale_id;
                    if (!isset($saleTotalsBySaleId[$saleId])) {
                        $saleTotalsBySaleId[$saleId] = 0;
                    }
                    $saleTotalsBySaleId[$saleId] += $overrideAmount;
                }

                foreach ($saleTotalsBySaleId as $saleId => $saleAmount) {
                    Sale::where('company_id', $companyId)
                        ->where('id', $saleId)
                        ->decrement('net_total', $saleAmount);
                }
            }

            if ($approvalItems->isNotEmpty()) {
                foreach ($approvalItems as $approvalItem) {
                    $itemSet = $approvalItem->itemSet;
                    if (!$itemSet) {
                        continue;
                    }

                    $rate = (float) ($itemSet->metal_rate ?? 1);
                    $calculatedAmount = (float) ($approvalItem->total_amount ?? ((float) $approvalItem->net_weight * $rate));
                    $rowKey = 'approval_' . (int) $approvalItem->id;
                    $amount = isset($payloadMap[$rowKey]['total_amount'])
                        ? (float) $payloadMap[$rowKey]['total_amount']
                        : $calculatedAmount;

                    $returnItemPayload = [
                        'sale_return_id' => $return->id,
                        'sale_item_id' => null,
                        'return_amount' => $amount,
                    ];

                    if ($hasItemsetIdColumn) {
                        $returnItemPayload['itemset_id'] = $itemSet->id;
                    }
                    if ($hasProductIdColumn) {
                        $returnItemPayload['product_id'] = $approvalItem->item_id;
                    }

                    SaleReturnItem::create($returnItemPayload);

                    $approvalItem->update(['status' => 'returned']);
                    $itemSet->update(['is_sold' => 0]);
                    $total += $amount;
                }

                foreach ($approvalIds as $approvalId) {
                    $this->refreshApprovalStatus((int) $approvalId);
                }
            }

            $return->update(['return_total' => $total]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Return processed successfully. Voucher created: {$return->return_voucher_no}",
                'data' => [
                    'return_id' => $return->id,
                    'return_voucher_no' => $return->return_voucher_no,
                    'return_total' => (float) $return->return_total,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    private function refreshApprovalStatus(int $approvalId): void
    {
        $total = ApprovalItem::where('approval_id', $approvalId)->count();
        $done = ApprovalItem::where('approval_id', $approvalId)
            ->whereIn('status', ['sold', 'returned'])
            ->count();

        $status = ($total > 0 && $total === $done) ? 'closed' : 'partial';
        ApprovalHeader::where('id', $approvalId)->update(['status' => $status]);
    }
}
