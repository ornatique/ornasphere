<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\ReturnCart;
use App\Models\ApprovalHeader;
use App\Models\ApprovalItem;
use Illuminate\Support\Facades\Schema;
use App\Models\ItemSet;
use App\Models\Company;

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
                    'remarks' => $return->remarks,
                    'customer_name' => $customerName,
                    'return_total' => (float) $return->return_total,
                    'refund_paid_amount' => (float) ($return->refund_paid_amount ?? 0),
                    'refund_mode' => $return->refund_mode,
                    'refund_reference' => $return->refund_reference,
                    'refund_note' => $return->refund_note,
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
            $received = (float) ($sale->received_amount ?? 0);
            $refundPaid = (float) ($sale->paid_amount ?? 0);
            $pending = max(0, (float) ($sale->net_total ?? 0) - ($received - $refundPaid));

            return [
                'sale_id' => $sale->id,
                'voucher_no' => $sale->voucher_no,
                'customer_name' => optional($sale->customer)->name,
                'sale_date' => optional($sale->sale_date)->format('Y-m-d') ?? null,
                'net_total' => (float) $sale->net_total,
                'received_amount' => $received,
                'refund_paid_amount' => $refundPaid,
                'pending_amount' => $pending,
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

    public function show($id)
    {
        $user = auth()->user();

        $return = SaleReturn::with([
            'sale.customer',
            'approval.customer',
            'items.saleItem.sale.customer',
            'items.saleItem.itemset.item',
            'items.itemSet.item',
        ])
            ->where('company_id', $user->company_id)
            ->findOrFail((int) $id);

        $customers = collect();
        if ($return->sale && $return->sale->customer) {
            $customers->push($return->sale->customer);
        }
        if ($return->approval && $return->approval->customer) {
            $customers->push($return->approval->customer);
        }
        foreach ($return->items as $row) {
            $saleCustomer = optional(optional($row->saleItem)->sale)->customer;
            if ($saleCustomer) {
                $customers->push($saleCustomer);
            }
        }

        $uniqueCustomers = $customers
            ->filter()
            ->unique('id')
            ->values()
            ->map(function ($c) {
                return [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                    'mobile_no' => $c->mobile_no,
                    'city' => $c->city,
                    'area' => $c->area,
                ];
            });

        $customer = $uniqueCustomers->first();
        $customerName = $customer['name'] ?? '-';

        return response()->json([
            'success' => true,
            'message' => 'Return voucher fetched successfully.',
            'data' => array_merge($return->toArray(), [
                'customer_name' => $customerName,
                'customer' => $customer,
                'customers' => $uniqueCustomers,
            ]),
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

        $qrCode = trim((string) $request->qr_code);
        $customerId = (int) $request->customer_id;

        $hasReturnApprovalItemId = Schema::hasColumn('sale_return_items', 'approval_item_id');
        $hasReturnItemsetId = Schema::hasColumn('sale_return_items', 'itemset_id');

        $approvalItem = ApprovalItem::with(['approval.customer', 'itemSet.item', 'legacyItemSet.item', 'item'])
            ->whereHas('approval', function ($q) use ($user, $customerId) {
                $q->where('company_id', $user->company_id)
                    ->where('customer_id', $customerId);
            })
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(status) = ?', ['pending']);
            })
            ->where(function ($q) use ($qrCode, $user) {
                $q->where('qr_code', $qrCode)
                    ->orWhereHas('itemSet', function ($itemSetQuery) use ($qrCode, $user) {
                        $itemSetQuery->where('company_id', $user->company_id)
                            ->where('qr_code', $qrCode);
                    })
                    ->orWhereHas('legacyItemSet', function ($itemSetQuery) use ($qrCode, $user) {
                        $itemSetQuery->where('company_id', $user->company_id)
                            ->where('qr_code', $qrCode);
                    });
            })
            ->when($hasReturnApprovalItemId, function ($q) {
                $q->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('sale_return_items')
                        ->whereColumn('sale_return_items.approval_item_id', 'approval_items.id');
                });
            })
            ->when(!$hasReturnApprovalItemId && $hasReturnItemsetId, function ($q) {
                $q->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('sale_return_items')
                        ->whereColumn('sale_return_items.itemset_id', 'approval_items.itemset_id');
                });
            })
            ->latest('id')
            ->first();

        if (!$approvalItem) {
            return response()->json([
                'success' => false,
                'message' => 'Product not approved to this customer or already returned'
            ], 404);
        }

        $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
        $item = optional($itemSet)->item ?? $approvalItem->item;
        $gross = (float) ($approvalItem->gross_weight ?? optional($itemSet)->gross_weight ?? 0);
        $otherWeight = (float) ($approvalItem->other_weight ?? optional($itemSet)->other ?? 0);
        $net = (float) ($approvalItem->net_weight ?? optional($itemSet)->net_weight ?? max(0, $gross - $otherWeight));
        $purity = (float) ($approvalItem->purity ?? optional($item)->outward_purity ?? 0);
        $wastePercent = (float) ($approvalItem->waste_percent ?? 0);
        $netPurity = (float) ($approvalItem->net_purity ?? max(0, $purity - $wastePercent));
        $fineWeight = (float) ($approvalItem->total_fine_weight ?? (($net * $netPurity) / 100));
        $metalAmount = (float) ($approvalItem->metal_amount ?? 0);
        $labourAmount = (float) ($approvalItem->labour_amount ?? 0);
        $otherAmount = $this->resolveOtherAmount($approvalItem->other_amount ?? null, optional($itemSet)->sale_other ?? 0);
        $totalAmount = $this->resolveTotalAmount($approvalItem->total_amount ?? null, $metalAmount, $labourAmount, $otherAmount);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $approvalItem->id,
                'sale_item_id' => null,
                'sale_id' => null,
                'approval_item_id' => $approvalItem->id,
                'approval_id' => $approvalItem->approval_id,
                'voucher_no' => optional($approvalItem->approval)->approval_no,
                'customer' => optional(optional($approvalItem->approval)->customer)->name,
                'itemset_id' => optional($itemSet)->id,
                'item_id' => $approvalItem->item_id ?? optional($itemSet)->item_id,
                'huid' => $approvalItem->huid ?? optional($itemSet)->HUID,
                'qr_code' => $approvalItem->qr_code ?? optional($itemSet)->qr_code,
                'item_name' => optional($item)->item_name,
                'name' => optional($item)->item_name,
                'gross_weight' => number_format($gross, 3, '.', ''),
                'other_weight' => number_format($otherWeight, 3, '.', ''),
                'net_weight' => number_format($net, 3, '.', ''),
                'purity' => number_format($purity, 3, '.', ''),
                'waste_percent' => number_format($wastePercent, 3, '.', ''),
                'net_purity' => number_format($netPurity, 3, '.', ''),
                'fine_weight' => number_format($fineWeight, 3, '.', ''),
                'metal_rate' => number_format((float) ($approvalItem->metal_rate ?? 0), 2, '.', ''),
                'metal_amount' => number_format($metalAmount, 2, '.', ''),
                'labour_rate' => number_format((float) ($approvalItem->labour_rate ?? 0), 2, '.', ''),
                'labour_amount' => number_format($labourAmount, 2, '.', ''),
                'other_amount' => number_format($otherAmount, 2, '.', ''),
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'remarks' => (string) ($approvalItem->remarks ?? ''),
                'source' => 'approval',
            ]
        ]);
    }

    public function returnCartList()
    {
        $user = auth()->user();

        $items = ReturnCart::with([
                'saleItem.itemset.item',
                'approvalItem.itemSet.item',
                'approvalItem.legacyItemSet.item',
                'approvalItem.item',
            ])
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->latest()
            ->get()
            ->map(function ($cart) {
                $saleItem = $cart->saleItem;
                if ($saleItem && $saleItem->itemset) {
                    return [
                        'cart_id' => $cart->id,
                        'source' => 'sale',
                        'sale_item_id' => $saleItem->id,
                        'approval_item_id' => null,
                        'itemset_id' => $saleItem->itemset_id,
                        'serial_no' => $saleItem->itemset->serial_no,
                        'qr_code' => $saleItem->itemset->qr_code,
                        'huid' => $saleItem->itemset->HUID,
                        'item_id' => $saleItem->itemset->item_id,
                        'item_name' => optional($saleItem->itemset->item)->item_name,
                        'gross_weight' => number_format((float) ($saleItem->gross_weight ?? $saleItem->itemset->gross_weight ?? 0), 3, '.', ''),
                        'other_weight' => number_format((float) ($saleItem->other_weight ?? $saleItem->itemset->other ?? 0), 3, '.', ''),
                        'net_weight' => number_format((float) ($saleItem->net_weight ?? $saleItem->itemset->net_weight ?? 0), 3, '.', ''),
                        'purity' => number_format((float) ($saleItem->purity ?? optional($saleItem->itemset->item)->purity ?? 0), 3, '.', ''),
                        'waste_percent' => number_format((float) ($saleItem->waste_percent ?? 0), 3, '.', ''),
                        'net_purity' => number_format((float) ($saleItem->net_purity ?? 0), 3, '.', ''),
                        'fine_weight' => number_format((float) ($saleItem->fine_weight ?? 0), 3, '.', ''),
                        'metal_rate' => number_format((float) ($saleItem->metal_rate ?? 0), 2, '.', ''),
                        'metal_amount' => number_format((float) ($saleItem->metal_amount ?? 0), 2, '.', ''),
                        'labour_rate' => number_format((float) ($saleItem->labour_rate ?? 0), 2, '.', ''),
                        'labour_amount' => number_format((float) ($saleItem->labour_amount ?? 0), 2, '.', ''),
                        'other_amount' => number_format((float) ($saleItem->other_amount ?? 0), 2, '.', ''),
                        'total_amount' => number_format((float) ($saleItem->total_amount ?? 0), 2, '.', ''),
                        'amount' => (float) ($saleItem->total_amount ?? 0),
                    ];
                }

                $approvalItem = $cart->approvalItem;
                if (!$approvalItem) {
                    return null;
                }

                $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                $item = optional($itemSet)->item ?? $approvalItem->item;
                $gross = (float) ($approvalItem->gross_weight ?? optional($itemSet)->gross_weight ?? 0);
                $otherWeight = (float) ($approvalItem->other_weight ?? optional($itemSet)->other ?? 0);
                $net = (float) ($approvalItem->net_weight ?? optional($itemSet)->net_weight ?? max(0, $gross - $otherWeight));
                $purity = (float) ($approvalItem->purity ?? optional($item)->outward_purity ?? optional($item)->purity ?? 0);
                $wastePercent = (float) ($approvalItem->waste_percent ?? 0);
                $netPurity = (float) ($approvalItem->net_purity ?? max(0, $purity - $wastePercent));
                $fineWeight = (float) ($approvalItem->total_fine_weight ?? (($net * $netPurity) / 100));
                $metalAmount = (float) ($approvalItem->metal_amount ?? 0);
                $labourAmount = (float) ($approvalItem->labour_amount ?? 0);
                $otherAmount = $this->resolveOtherAmount($approvalItem->other_amount ?? null, optional($itemSet)->sale_other ?? 0);
                $totalAmount = $this->resolveTotalAmount($approvalItem->total_amount ?? null, $metalAmount, $labourAmount, $otherAmount);

                return [
                    'cart_id' => $cart->id,
                    'source' => 'approval',
                    'sale_item_id' => null,
                    'approval_item_id' => $approvalItem->id,
                    'approval_id' => $approvalItem->approval_id,
                    'itemset_id' => optional($itemSet)->id,
                    'serial_no' => optional($itemSet)->serial_no,
                    'qr_code' => $approvalItem->qr_code ?? optional($itemSet)->qr_code,
                    'huid' => $approvalItem->huid ?? optional($itemSet)->HUID,
                    'item_id' => $approvalItem->item_id ?? optional($itemSet)->item_id,
                    'item_name' => optional($item)->item_name,
                    'gross_weight' => number_format($gross, 3, '.', ''),
                    'other_weight' => number_format($otherWeight, 3, '.', ''),
                    'net_weight' => number_format($net, 3, '.', ''),
                    'purity' => number_format($purity, 3, '.', ''),
                    'waste_percent' => number_format($wastePercent, 3, '.', ''),
                    'net_purity' => number_format($netPurity, 3, '.', ''),
                    'fine_weight' => number_format($fineWeight, 3, '.', ''),
                    'metal_rate' => number_format((float) ($approvalItem->metal_rate ?? 0), 2, '.', ''),
                    'metal_amount' => number_format($metalAmount, 2, '.', ''),
                    'labour_rate' => number_format((float) ($approvalItem->labour_rate ?? 0), 2, '.', ''),
                    'labour_amount' => number_format($labourAmount, 2, '.', ''),
                    'other_amount' => number_format($otherAmount, 2, '.', ''),
                    'total_amount' => number_format($totalAmount, 2, '.', ''),
                    'remarks' => (string) ($approvalItem->remarks ?? ''),
                    'amount' => $totalAmount,
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
            'remarks' => 'nullable|string',
            'refund_paid_amount' => 'nullable|numeric|min:0',
            'refund_mode' => 'nullable|string|max:30',
            'refund_reference' => 'nullable|string|max:120',
            'refund_note' => 'nullable|string|max:255',
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
            $cartSaleIds = $cartItems
                ->pluck('saleItem.sale_id')
                ->filter()
                ->unique()
                ->values();

            if ($cartSaleIds->count() !== 1 || (int) $cartSaleIds->first() !== $saleId) {
                throw new \Exception('Scanned items do not belong to selected sale.');
            }

            $return = SaleReturn::create([
                'company_id' => $user->company_id,
                'sale_id' => $saleId,
                'return_voucher_no' => 'SR' . time(),
                'return_date' => now(),
                'remarks' => $request->input('remarks', $request->input('remark')),
                'return_total' => 0,
                'refund_paid_amount' => 0,
                'refund_mode' => $request->input('refund_mode'),
                'refund_reference' => $request->input('refund_reference'),
                'refund_note' => $request->input('refund_note'),
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
                'return_total' => $total,
                'refund_paid_amount' => (float) ($request->input('refund_paid_amount', 0) > 0
                    ? $request->input('refund_paid_amount', 0)
                    : $total),
            ]);

            $sale->decrement('net_total', $total);
            $sale->increment('paid_amount', (float) ($return->refund_paid_amount ?? 0));

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
        $companyId = $request->user()->company_id;
        $customerId = (int) ($request->input('customer_id') ?? 0);
        $rawSubmittedIds = $request->input('approval_item_ids');

        if (!is_array($rawSubmittedIds) || empty($rawSubmittedIds)) {
            $rawSubmittedIds = $request->input('return_items', []);
        }

        $submittedApprovalIds = collect(is_array($rawSubmittedIds) ? $rawSubmittedIds : [$rawSubmittedIds])
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values();
        $approvalItemIds = collect();
        $rowPayloads = collect();
        $unresolvedItems = collect();

        Log::info('Approval return store request received.', [
            'user_id' => optional($request->user())->id,
            'company_id' => $companyId,
            'customer_id' => $customerId ?: null,
            'return_items' => $request->input('return_items', []),
            'approval_item_ids' => $request->input('approval_item_ids', []),
            'submitted_ids' => $submittedApprovalIds->all(),
        ]);

        foreach ($submittedApprovalIds as $submittedId) {
            $approvalItemId = ApprovalItem::where('id', $submittedId)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhere('status', '!=', 'returned');
                })
                ->where(function ($q) use ($companyId, $customerId) {
                    $this->scopeApprovalReturnCompany($q, $companyId, $customerId);
                })
                ->value('id');

            if (!$approvalItemId) {
                $approvalItemId = ApprovalItem::where(function ($q) use ($submittedId) {
                    $q->where('itemset_id', $submittedId)
                        ->orWhere(function ($legacyQuery) use ($submittedId) {
                            $legacyQuery->whereNull('itemset_id')
                                ->where('item_id', $submittedId);
                        });
                })
                    ->where(function ($q) {
                        $q->whereNull('status')
                            ->orWhere('status', '!=', 'returned');
                    })
                    ->where(function ($q) use ($companyId, $customerId) {
                        $this->scopeApprovalReturnCompany($q, $companyId, $customerId);
                    })
                    ->latest('id')
                    ->value('id');
            }

            if ($approvalItemId) {
                $approvalItemIds->push((int) $approvalItemId);
                Log::info('Approval return store item resolved.', [
                    'submitted_id' => $submittedId,
                    'approval_item_id' => (int) $approvalItemId,
                ]);
            } else {
                $candidates = $this->approvalReturnCandidates($submittedId);
                $unresolvedItems->push([
                    'submitted_id' => $submittedId,
                    'candidates' => $candidates,
                ]);

                Log::warning('Approval return store item could not be resolved.', [
                    'submitted_id' => $submittedId,
                    'company_id' => $companyId,
                    'customer_id' => $customerId ?: null,
                    'candidates' => $candidates,
                ]);
            }
        }

        // Store API is approval-return only. items[] remains supported for app clients
        // that submit scanned approval rows instead of approval_item_ids directly.
        if ($request->has('items') && is_array($request->input('items'))) {
            foreach ((array) $request->input('items', []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $approvalItemId = (int) ($row['approval_item_id'] ?? 0);
                $totalAmount = (float) ($row['total_amount'] ?? $row['amount'] ?? 0);

                if ($approvalItemId <= 0 && !empty($row['itemset_id'])) {
                    $approvalItemId = (int) ApprovalItem::where('itemset_id', (int) $row['itemset_id'])
                        ->where('status', 'pending')
                        ->whereHas('approval', function ($q) use ($companyId, $customerId) {
                            $q->where('company_id', $companyId);
                            if ($customerId > 0) {
                                $q->where('customer_id', $customerId);
                            }
                        })
                        ->latest('id')
                        ->value('id');
                }

                if ($approvalItemId <= 0) {
                    continue;
                }

                $approvalItemIds->push($approvalItemId);
                $rowPayloads->push([
                    'type' => 'approval',
                    'id' => $approvalItemId,
                    'total_amount' => $totalAmount,
                ]);
            }
        }

        $approvalItemIds = $approvalItemIds
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values();

        if ($approvalItemIds->isEmpty()) {
            $hasCompanyMismatch = $unresolvedItems
                ->pluck('candidates')
                ->flatten(1)
                ->contains(function ($candidate) use ($companyId) {
                    return (int) ($candidate['approval_company_id'] ?? 0) !== (int) $companyId
                        && (int) ($candidate['itemset_company_id'] ?? 0) !== (int) $companyId;
                });

            Log::warning('Approval return store rejected: no available approval items.', [
                'user_id' => optional($request->user())->id,
                'company_id' => $companyId,
                'customer_id' => $customerId ?: null,
                'submitted_ids' => $submittedApprovalIds->all(),
            ]);

            if ($hasCompanyMismatch) {
                return response()->json([
                    'success' => false,
                    'code' => 'APPROVAL_ITEM_COMPANY_MISMATCH',
                    'message' => 'Selected approval item belongs to another company. Please log in with the correct company account.',
                    'authenticated_company_id' => (int) $companyId,
                ], 403);
            }

            return response()->json([
                'success' => false,
                'message' => 'Please select at least one available approval item for return.'
            ], 422);
        }

        $request->merge([
            'sale_item_ids' => [],
            'approval_item_ids' => $approvalItemIds->all(),
            'row_payloads' => $rowPayloads->values()->all(),
            'approval_store_only' => true,
        ]);

        Log::info('Approval return store forwarding resolved items.', [
            'user_id' => optional($request->user())->id,
            'company_id' => $companyId,
            'approval_item_ids' => $request->input('approval_item_ids', []),
        ]);

        return $this->processSelected($request);
    }

    private function approvalReturnCandidates(int $submittedId): array
    {
        return ApprovalItem::with(['approval', 'itemSet', 'legacyItemSet'])
            ->where(function ($query) use ($submittedId) {
                $query->where('id', $submittedId)
                    ->orWhere('itemset_id', $submittedId)
                    ->orWhere(function ($legacyQuery) use ($submittedId) {
                        $legacyQuery->whereNull('itemset_id')
                            ->where('item_id', $submittedId);
                    });
            })
            ->get()
            ->map(function ($row) {
                $itemSet = $row->itemSet ?? $row->legacyItemSet;

                return [
                    'approval_item_id' => (int) $row->id,
                    'approval_id' => (int) $row->approval_id,
                    'approval_company_id' => optional($row->approval)->company_id,
                    'approval_customer_id' => optional($row->approval)->customer_id,
                    'itemset_id' => $row->itemset_id,
                    'item_id' => $row->item_id,
                    'itemset_company_id' => optional($itemSet)->company_id,
                    'status' => $row->status,
                ];
            })
            ->values()
            ->all();
    }

    public function pdf($returnId)
    {
        $user = auth()->user();

        $return = SaleReturn::with([
            'sale.customer',
            'approval.customer',
            'approval.items.itemSet.item',
            'items.saleItem.itemset.item',
            'items.approvalItem.itemSet.item',
            'items.approvalItem.legacyItemSet.item',
            'items.approvalItem.item',
            'items.itemSet.item',
        ])
            ->where('company_id', $user->company_id)
            ->findOrFail($returnId);

        $pdf = Pdf::loadView('company.returns.return_pdf', compact('return'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('Return-' . $return->return_voucher_no . '.pdf');
    }

    public function exportListPdf(Request $request)
    {
        $user = $request->user();
        $company = Company::findOrFail($user->company_id);
        [$fromDate, $toDate] = $this->resolveReturnDateRange($request);

        $query = SaleReturn::with([
            'approval.customer',
            'approval.creator',
            'approval.items.itemSet.item',
            'approval.items.item',
            'items.itemSet.item',
        ])
            ->where('company_id', $company->id)
            ->where('source_type', 'approval')
            ->whereBetween('return_date', [$fromDate, $toDate])
            ->latest('return_date')
            ->latest('id');

        if ($request->filled('customer_id')) {
            $query->whereHas('approval', function ($approvalQuery) use ($request) {
                $approvalQuery->where('customer_id', (int) $request->customer_id);
            });
        }

        $rows = $query->get()
            ->map(fn(SaleReturn $return) => $this->summarizeApprovalReturn($return))
            ->values();

        $fileName = 'approval-return-list-' . now()->format('YmdHis') . '.pdf';
        $pdf = Pdf::loadView('company.returns.list_pdf', compact('company', 'rows', 'fromDate', 'toDate'))
            ->setPaper('a4', 'landscape');

        if ($request->boolean('download')) {
            return $pdf->download($fileName);
        }

        return $pdf->stream($fileName);
    }

    private function resolveReturnDateRange(Request $request): array
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $today = now()->toDateString();

        if (empty($fromDate) && empty($toDate)) {
            return [$today, $today];
        }

        return [$fromDate ?: $toDate, $toDate ?: $fromDate];
    }

    private function summarizeApprovalReturn(SaleReturn $return): array
    {
        if (isset($return->approvalReturnSummary)) {
            return $return->approvalReturnSummary;
        }

        $approvalItemsByItemSet = collect(optional($return->approval)->items ?? [])
            ->filter(fn($item) => !empty($item->itemset_id))
            ->keyBy('itemset_id');
        $remainingApprovalItems = collect(optional($return->approval)->items ?? [])
            ->where('status', 'returned')
            ->values();
        $itemNames = collect();
        $totals = [
            'qty' => 0,
            'gross_wt' => 0.0,
            'net_wt' => 0.0,
            'fine_wt' => 0.0,
            'metal_amt' => 0.0,
            'labour_amt' => 0.0,
            'other_amt' => 0.0,
            'total_amt' => 0.0,
        ];

        foreach ($return->items as $returnItem) {
            $itemSet = $returnItem->itemSet;
            $approvalItem = $itemSet
                ? $approvalItemsByItemSet->get($itemSet->id)
                : null;

            if (!$approvalItem) {
                $approvalItem = $remainingApprovalItems->first(function ($item) use ($returnItem) {
                    return abs((float) ($item->total_amount ?? 0) - (float) ($returnItem->return_amount ?? 0)) < 0.01;
                }) ?? $remainingApprovalItems->first();
            }

            if ($approvalItem) {
                $remainingApprovalItems = $remainingApprovalItems
                    ->reject(fn($item) => (int) $item->id === (int) $approvalItem->id)
                    ->values();
            }

            $itemSet = $itemSet ?? optional($approvalItem)->itemSet;
            $itemNames->push(
                optional(optional($itemSet)->item)->item_name
                ?? optional(optional($approvalItem)->item)->item_name
            );
            $totals['qty']++;
            $totals['gross_wt'] += (float) ($approvalItem->gross_weight ?? 0);
            $totals['net_wt'] += (float) ($approvalItem->net_weight ?? 0);
            $totals['fine_wt'] += (float) ($approvalItem->total_fine_weight ?? 0);
            $totals['metal_amt'] += (float) ($approvalItem->metal_amount ?? 0);
            $totals['labour_amt'] += (float) ($approvalItem->labour_amount ?? 0);
            $totals['other_amt'] += (float) ($approvalItem->other_amount ?? 0);
            $totals['total_amt'] += (float) ($returnItem->return_amount ?? 0);
        }

        return $return->approvalReturnSummary = [
            'return_id' => (int) $return->id,
            'voucher_no' => (string) ($return->return_voucher_no ?? '-'),
            'return_date' => $return->return_date
                ? \Carbon\Carbon::parse($return->return_date)->format('d-m-Y')
                : '-',
            'customer_name' => optional(optional($return->approval)->customer)->name ?? '-',
            'item_names' => $itemNames->filter()->unique()->implode(', ') ?: '-',
            'created_by' => optional(optional($return->approval)->creator)->name ?? '-',
        ] + $totals;
    }

    // Add Label Return Approval (same as web button flow)
    public function approvalReturnItems(Request $request)
    {
    
        $companyId = $request->user()->company_id;
        $customerId = (int) $request->input('customer_id', 0);
        $qrCode = trim((string) $request->input('qr_code', ''));

        if ($customerId <= 0) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $query = ApprovalItem::with('itemSet.item', 'legacyItemSet.item', 'item')
            ->whereExists(function ($q) use ($companyId, $customerId) {
                $q->select(DB::raw(1))
                    ->from('approval_headers')
                    ->whereColumn('approval_headers.id', 'approval_items.approval_id')
                    ->where('approval_headers.company_id', $companyId)
                    ->where('approval_headers.customer_id', $customerId);
            })
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(TRIM(status)) = ?', ['pending']);
            })
            ->when($qrCode !== '', function ($q) use ($qrCode) {
                $q->whereRaw('TRIM(qr_code) = ?', [$qrCode]);
            });

        $rows = $query->get()
            ->map(function ($row) {
                $itemSet = $row->itemSet ?? $row->legacyItemSet;
                $item = optional($itemSet)->item ?? $row->item;
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
                $otherAmount = $this->resolveOtherAmount($row->other_amount ?? null, optional($itemSet)->sale_other ?? 0);
                $totalAmount = $this->resolveTotalAmount($row->total_amount ?? null, $metalAmount, $labourAmount, $otherAmount);

                return [
                    'source' => 'approval',
                    'id' => $row->id,
                    'approval_item_id' => $row->id,
                    'approval_id' => $row->approval_id,
                    'item_id' => $row->item_id ?? optional($itemSet)->item_id,
                    'itemset_id' => optional($itemSet)->id,
                    'qr_code' => $row->qr_code ?? optional($itemSet)->qr_code,
                    'huid' => $row->huid ?? optional($itemSet)->HUID,
                    'name' => optional($item)->item_name,
                    'item_name' => optional($item)->item_name,
                    'gross_weight' => number_format($gross, 3, '.', ''),
                    'other_weight' => number_format($otherWeight, 3, '.', ''),
                    'net_weight' => number_format($net, 3, '.', ''),
                    'purity' => number_format($purity, 3, '.', ''),
                    'waste_percent' => number_format($wastePercent, 3, '.', ''),
                    'net_purity' => number_format($netPurity, 3, '.', ''),
                    'fine_weight' => number_format($fineWeight, 3, '.', ''),
                    'metal_rate' => number_format($metalRate, 2, '.', ''),
                    'metal_amount' => number_format($metalAmount, 2, '.', ''),
                    'labour_rate' => number_format($labourRate, 2, '.', ''),
                    'labour_amount' => number_format($labourAmount, 2, '.', ''),
                    'other_amount' => number_format($otherAmount, 2, '.', ''),
                    'total_amount' => number_format($totalAmount, 2, '.', ''),
                    'remarks' => (string) ($row->remarks ?? ''),
                ];
            })
            ->values();

        if ($rows->isEmpty() && $qrCode !== '') {
            $qrExists = ApprovalItem::whereRaw('TRIM(qr_code) = ?', [$qrCode])->exists();
            $qrExistsForCompany = ApprovalItem::whereRaw('TRIM(qr_code) = ?', [$qrCode])
                ->whereExists(function ($q) use ($companyId) {
                    $q->select(DB::raw(1))
                        ->from('approval_headers')
                        ->whereColumn('approval_headers.id', 'approval_items.approval_id')
                        ->where('approval_headers.company_id', $companyId);
                })
                ->exists();
            $qrExistsForCustomer = ApprovalItem::whereRaw('TRIM(qr_code) = ?', [$qrCode])
                ->whereExists(function ($q) use ($companyId, $customerId) {
                    $q->select(DB::raw(1))
                        ->from('approval_headers')
                        ->whereColumn('approval_headers.id', 'approval_items.approval_id')
                        ->where('approval_headers.company_id', $companyId)
                        ->where('approval_headers.customer_id', $customerId);
                })
                ->exists();

            $message = 'Product not approved to this customer or already returned';
            if (!$qrExists) {
                $message = 'QR code not found in approval items';
            } elseif (!$qrExistsForCompany) {
                $message = 'This QR code does not belong to your company';
            } elseif (!$qrExistsForCustomer) {
                $message = 'This QR code is not approved to this customer';
            }

            $payload = [
                'success' => false,
                'message' => $message,
                'data' => []
            ];

            if ($request->boolean('debug')) {
                $payload['debug'] = [
                    'auth_company_id' => $companyId,
                    'customer_id' => $customerId,
                    'qr_code' => $qrCode,
                    'qr_exists' => $qrExists,
                    'qr_exists_for_company' => $qrExistsForCompany,
                    'qr_exists_for_customer' => $qrExistsForCustomer,
                ];
            }

            return response()->json($payload, 404);
        }

        if ($rows->isEmpty() && $request->boolean('debug')) {
            $debug = [
                'auth_company_id' => $companyId,
                'customer_id' => $customerId,
                'qr_code' => $qrCode,
                'qr_found_in_approval_items' => $qrCode === '' ? null : ApprovalItem::whereRaw('TRIM(qr_code) = ?', [$qrCode])->count(),
                'qr_found_for_company_customer' => $qrCode === '' ? null : ApprovalItem::whereRaw('TRIM(qr_code) = ?', [$qrCode])
                    ->whereExists(function ($q) use ($companyId, $customerId) {
                        $q->select(DB::raw(1))
                            ->from('approval_headers')
                            ->whereColumn('approval_headers.id', 'approval_items.approval_id')
                            ->where('approval_headers.company_id', $companyId)
                            ->where('approval_headers.customer_id', $customerId);
                    })
                    ->count(),
                'qr_found_pending_for_company_customer' => $qrCode === '' ? null : ApprovalItem::whereRaw('TRIM(qr_code) = ?', [$qrCode])
                    ->whereExists(function ($q) use ($companyId, $customerId) {
                        $q->select(DB::raw(1))
                            ->from('approval_headers')
                            ->whereColumn('approval_headers.id', 'approval_items.approval_id')
                            ->where('approval_headers.company_id', $companyId)
                            ->where('approval_headers.customer_id', $customerId);
                    })
                    ->where(function ($q) {
                        $q->whereNull('status')
                            ->orWhereRaw('LOWER(TRIM(status)) = ?', ['pending']);
                    })
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [],
                'debug' => $debug,
            ]);
        }

        $cartAdded = false;
        $cartId = null;
        if ($qrCode !== '' && $rows->isNotEmpty()) {
            if (!Schema::hasColumn('return_carts', 'approval_item_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Return cart approval item column missing. Please run migration.',
                    'data' => []
                ], 500);
            }

            $approvalItemId = (int) $rows->first()['approval_item_id'];
            $existingCart = ReturnCart::where('user_id', $request->user()->id)
                ->where('company_id', $companyId)
                ->where('approval_item_id', $approvalItemId)
                ->first();

            if ($existingCart) {
                $cartId = $existingCart->id;
            } else {
                $cart = ReturnCart::create([
                    'user_id' => $request->user()->id,
                    'company_id' => $companyId,
                    'sale_item_id' => null,
                    'approval_item_id' => $approvalItemId,
                ]);
                $cartAdded = true;
                $cartId = $cart->id;
            }
        }

        return response()->json([
            'success' => true,
            'cart_added' => $cartAdded,
            'cart_id' => $cartId,
            'data' => $rows
        ]);
    }

    // Process mixed return: sale items + approval items in one voucher
    public function processSelected(Request $request)
    {
        $companyId = $request->user()->company_id;
        $request->validate([
            'customer_id' => 'required|integer',
            'refund_paid_amount' => 'nullable|numeric|min:0',
            'refund_mode' => 'nullable|string|max:30',
            'refund_reference' => 'nullable|string|max:120',
            'refund_note' => 'nullable|string|max:255',
        ]);
        $customerId = (int) $request->customer_id;

        $saleItemIds = collect($request->input('sale_item_ids', []))
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($saleItemIds->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending approval items can be returned from Approval Return.'
            ], 422);
        }

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
            $hasApprovalItemIdColumn = Schema::hasColumn('sale_return_items', 'approval_item_id');
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
                $approvalItems = ApprovalItem::with(['approval', 'itemSet', 'legacyItemSet'])
                    ->whereIn('id', $approvalItemIds)
                    ->where(function ($statusQuery) {
                        $statusQuery->whereNull('status')
                            ->orWhereRaw('LOWER(status) = ?', ['pending']);
                    })
                    ->whereHas('approval', function ($approvalQuery) use ($companyId, $customerId) {
                        $approvalQuery->where('company_id', $companyId)
                            ->where('customer_id', $customerId);
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
                'remarks' => $request->input('remarks', $request->input('remark')),
                'return_total' => 0,
                'refund_paid_amount' => 0,
                'refund_mode' => $request->input('refund_mode'),
                'refund_reference' => $request->input('refund_reference'),
                'refund_note' => $request->input('refund_note'),
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
                    $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
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

                    if ($hasApprovalItemIdColumn) {
                        $returnItemPayload['approval_item_id'] = $approvalItem->id;
                    }
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

            $return->update([
                'return_total' => $total,
                'refund_paid_amount' => (float) ($request->input('refund_paid_amount', 0) > 0
                    ? $request->input('refund_paid_amount', 0)
                    : $total),
            ]);

            if ($saleItems->isNotEmpty()) {
                $saleTotalsBySaleId = [];
                foreach ($saleItems as $saleItem) {
                    $saleId = (int) $saleItem->sale_id;
                    if (!isset($saleTotalsBySaleId[$saleId])) {
                        $saleTotalsBySaleId[$saleId] = 0;
                    }
                    $rowKey = 'sale_' . (int) $saleItem->id;
                    $saleTotalsBySaleId[$saleId] += isset($payloadMap[$rowKey]['total_amount'])
                        ? (float) $payloadMap[$rowKey]['total_amount']
                        : (float) ($saleItem->total_amount ?? 0);
                }

                $totalSaleReturnAmount = array_sum($saleTotalsBySaleId);
                $refundPaid = (float) ($return->refund_paid_amount ?? 0);

                foreach ($saleTotalsBySaleId as $saleId => $saleAmount) {
                    $share = $totalSaleReturnAmount > 0
                        ? ($refundPaid * ($saleAmount / $totalSaleReturnAmount))
                        : 0;

                    Sale::where('company_id', $companyId)
                        ->where('id', $saleId)
                        ->increment('paid_amount', $share);
                }
            }

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
            Log::error('Approval return processing failed.', [
                'user_id' => optional($request->user())->id,
                'company_id' => $companyId,
                'approval_item_ids' => $approvalItemIds->all(),
                'message' => $e->getMessage(),
            ]);

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

    private function scopeApprovalReturnCompany($query, int $companyId, int $customerId = 0): void
    {
        if ($customerId > 0) {
            $query->whereHas('approval', function ($approvalQuery) use ($companyId, $customerId) {
                $approvalQuery->where('company_id', $companyId)
                    ->where('customer_id', $customerId);
            });
            return;
        }

        $query->whereHas('approval', function ($approvalQuery) use ($companyId) {
            $approvalQuery->where('company_id', $companyId);
        })
            ->orWhereHas('itemSet', function ($itemSetQuery) use ($companyId) {
                $itemSetQuery->where('company_id', $companyId);
            })
            ->orWhereHas('legacyItemSet', function ($itemSetQuery) use ($companyId) {
                $itemSetQuery->where('company_id', $companyId);
            });
    }

    public function returnsearchItemSets(Request $request)
    {
        $companyId = $request->user()->company_id;
        $customerId = (int) ($request->input('customer_id') ?? 0);
        $keyword = trim((string) $request->keyword);

        if ($keyword === '') {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $rows = ItemSet::with('item')
            ->where('company_id', $companyId)
            ->where('is_final', 1)
            ->where('is_sold', 1)
            ->when($customerId > 0, function ($q) use ($companyId, $customerId) {
                $q->whereIn('id', function ($sub) use ($companyId, $customerId) {
                    $sub->select('sale_items.itemset_id')
                        ->from('sale_items')
                        ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                        ->where('sales.company_id', $companyId)
                        ->where('sales.customer_id', $customerId);
                });
            })
            ->where(function ($q) use ($keyword) {
                $q->where('HUID', 'LIKE', "%{$keyword}%")
                    ->orWhere('qr_code', 'LIKE', "%{$keyword}%");
            })
            ->limit(20)
            ->get()
            ->map(function ($set) use ($companyId, $customerId) {
                $item = $set->item;

                $saleItem = SaleItem::with(['sale.customer'])
                    ->where('itemset_id', $set->id)
                    ->when($customerId > 0, function ($q) use ($companyId, $customerId) {
                        $q->whereHas('sale', function ($sq) use ($companyId, $customerId) {
                            $sq->where('company_id', $companyId)
                                ->where('customer_id', $customerId);
                        });
                    })
                    ->when($customerId <= 0, function ($q) use ($companyId) {
                        $q->whereHas('sale', function ($sq) use ($companyId) {
                            $sq->where('company_id', $companyId);
                        });
                    })
                    ->latest('id')
                    ->first();

                $approvalItem = ApprovalItem::with(['approval.customer'])
                    ->where('itemset_id', $set->id)
                    ->where('status', 'pending')
                    ->when($customerId > 0, function ($q) use ($companyId, $customerId) {
                        $q->whereHas('approval', function ($aq) use ($companyId, $customerId) {
                            $aq->where('company_id', $companyId)
                                ->where('customer_id', $customerId);
                        });
                    })
                    ->when($customerId <= 0, function ($q) use ($companyId) {
                        $q->whereHas('approval', function ($aq) use ($companyId) {
                            $aq->where('company_id', $companyId);
                        });
                    })
                    ->latest('id')
                    ->first();

                $source = $approvalItem ? 'approval' : 'sale';
                $gross = (float) ($set->gross_weight ?? 0);
                $otherWeight = (float) ($set->other ?? 0);
                $net = (float) ($set->net_weight ?? max(0, $gross - $otherWeight));
                $purity = (float) (optional($item)->outward_purity ?? 0);
                $wastePercent = 0.0;
                $netPurity = max(0, $purity - $wastePercent);
                $fineWeight = (float) (($net * $netPurity) / 100);
                $metalRate = 0.0;
                $metalAmount = 0.0;
                $labourRate = (float) ($set->sale_labour_rate ?? 0);
                $labourAmount = (float) ($set->sale_labour_amount ?? ($net * $labourRate));
                $otherAmount = (float) ($set->sale_other ?? 0);
                $totalAmount = $metalAmount + $labourAmount + $otherAmount;

                $customerName = '-';
                if ($source === 'approval') {
                    $customerName = optional(optional($approvalItem)->approval->customer)->name ?? '-';
                } else {
                    $customerName = optional(optional($saleItem)->sale->customer)->name ?? '-';
                }

                return [
                    'source' => $source,
                    'sale_item_id' => $saleItem?->id,
                    'approval_item_id' => $approvalItem?->id,
                    'itemset_id' => (int) $set->id,
                    'item_id' => (int) ($set->item_id ?? 0),
                    'huid' => $set->HUID,
                    'qr_code' => $set->qr_code,
                    'label' => $set->qr_code,
                    'item_name' => optional($item)->item_name,
                    'customer_name' => $customerName,
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
                    'raw_itemset' => $set,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    private function resolveOtherAmount($inputOtherAmount, $itemSetSaleOther): float
    {
        $baseOther = (float) ($itemSetSaleOther ?? 0);

        if ($inputOtherAmount === null || $inputOtherAmount === '') {
            return $baseOther;
        }

        $inputOther = (float) $inputOtherAmount;
        if ($inputOther <= 0 && $baseOther > 0) {
            return $baseOther;
        }

        return $inputOther;
    }

    private function resolveTotalAmount($inputTotalAmount, float $metalAmount, float $labourAmount, float $otherAmount): float
    {
        $calculated = (float) ($metalAmount + $labourAmount + $otherAmount);
        if ($inputTotalAmount === null || $inputTotalAmount === '') {
            return $calculated;
        }

        $inputTotal = (float) $inputTotalAmount;
        if ($inputTotal <= 0 && $calculated > 0) {
            return $calculated;
        }

        return $inputTotal;
    }
}
