<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalHeader;
use App\Models\ApprovalItem;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemSet;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalApiController extends Controller
{
    public function customers(Request $request)
    {
        $companyId = $request->user()->company_id;

        $customers = Customer::where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    public function items(Request $request)
    {
        $companyId = $request->user()->company_id;

        $items = Item::where('company_id', $companyId)
            ->orderBy('item_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = ApprovalHeader::with('customer')
            ->withCount([
                'items as active_items_count' => function ($q) {
                    $q->where('status', '!=', 'returned');
                }
            ])
            ->withSum([
                'items as active_net_weight' => function ($q) {
                    $q->where('status', '!=', 'returned');
                }
            ], 'net_weight')
            ->withSum([
                'items as active_item_amount' => function ($q) {
                    $q->where('status', '!=', 'returned');
                }
            ], 'total_amount')
            ->where('company_id', $companyId);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('approval_date', [$request->from_date, $request->to_date]);
        }

        $rows = $query->orderByDesc('approval_date')
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'approval_no' => $row->approval_no,
                    'approval_date' => optional($row->approval_date)->format('Y-m-d') ?? null,
                    'customer_id' => $row->customer_id,
                    'customer_name' => optional($row->customer)->name ?? '-',
                    'status' => $row->status,
                    'total_items' => (int) ($row->active_items_count ?? 0),
                    'total_net_weight' => (float) ($row->active_net_weight ?? 0),
                    'total_amount' => (float) ($row->active_item_amount ?? 0),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function getItemSets(Request $request, $itemId)
    {
        $companyId = $request->user()->company_id;

        $rows = ItemSet::with('item')
            ->where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where('is_final', 1)
            ->where('is_sold', 0)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function searchItemSets(Request $request)
    {
        $companyId = $request->user()->company_id;
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
            ->where('is_sold', 0)
            ->where(function ($q) use ($keyword) {
                $q->where('HUID', 'LIKE', "%{$keyword}%")
                    ->orWhere('qr_code', 'LIKE', "%{$keyword}%");
            })
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    // Exact QR scanner endpoint for app
    public function scanQr(Request $request)
    {
        $companyId = $request->user()->company_id;

        $request->validate([
            'qr_code' => 'required|string',
        ]);

        $row = ItemSet::with('item')
            ->where('company_id', $companyId)
            ->where('is_final', 1)
            ->where('is_sold', 0)
            ->where('qr_code', trim((string) $request->qr_code))
            ->first();

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'QR not found or already used',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $row,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'items' => 'required|array|min:1',
        ]);

        DB::beginTransaction();

        try {
            $approval = ApprovalHeader::create([
                'company_id' => $companyId,
                'customer_id' => $request->customer_id,
                'approval_no' => 'APP' . time(),
                'approval_date' => now(),
                'status' => 'open',
            ]);

            foreach ($request->items as $row) {
                $itemSetId = (int) ($row['itemset_id'] ?? $row['id'] ?? 0);
                if (!$itemSetId) {
                    continue;
                }

                $itemSet = ItemSet::with('item')
                    ->where('company_id', $companyId)
                    ->where('is_final', 1)
                    ->findOrFail($itemSetId);

                if ((int) $itemSet->is_sold === 1) {
                    throw new \Exception("Item already sold/used: {$itemSet->qr_code}");
                }

                $gross = (float) ($row['gross_weight'] ?? $itemSet->gross_weight ?? 0);
                $otherWeight = (float) ($row['other_weight'] ?? $itemSet->other ?? 0);
                $netWeight = (float) ($row['net_weight'] ?? ($gross - $otherWeight));
                $purity = (float) ($row['purity'] ?? optional($itemSet->item)->outward_purity ?? 0);
                $wastePercent = (float) ($row['waste_percent'] ?? 0);
                $netPurity = (float) ($row['net_purity'] ?? max(0, $purity - $wastePercent));
                $totalFineWeight = (float) ($row['total_fine_weight'] ?? ($netWeight * $netPurity / 100));
                $metalRate = (float) ($row['metal_rate'] ?? 0);
                $metalAmount = (float) ($row['metal_amount'] ?? ($netWeight * $metalRate));
                $labourRate = (float) ($row['labour_rate'] ?? $itemSet->sale_labour_rate ?? optional($itemSet->item)->labour_rate ?? 0);
                $labourAmount = (float) ($row['labour_amount'] ?? ($netWeight * $labourRate));
                $otherAmount = (float) ($row['other_amount'] ?? $itemSet->sale_other ?? 0);
                $totalAmount = (float) ($row['total_amount'] ?? ($metalAmount + $labourAmount + $otherAmount));

                ApprovalItem::create([
                    'approval_id' => $approval->id,
                    'itemset_id' => $itemSet->id,
                    'item_id' => $itemSet->item_id,
                    'huid' => $row['huid'] ?? $itemSet->HUID,
                    'qr_code' => $row['qr_code'] ?? $itemSet->qr_code,
                    'gross_weight' => $gross,
                    'other_weight' => $otherWeight,
                    'net_weight' => $netWeight,
                    'purity' => $purity,
                    'waste_percent' => $wastePercent,
                    'net_purity' => $netPurity,
                    'total_fine_weight' => $totalFineWeight,
                    'metal_rate' => $metalRate,
                    'metal_amount' => $metalAmount,
                    'labour_rate' => $labourRate,
                    'labour_amount' => $labourAmount,
                    'other_amount' => $otherAmount,
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                ]);

                $itemSet->update(['is_sold' => 1]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Approval created successfully',
                'data' => [
                    'approval_id' => $approval->id,
                    'approval_no' => $approval->approval_no,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $approval = ApprovalHeader::with('customer', 'items.itemSet.item', 'items.legacyItemSet.item')
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $items = $approval->items->map(function ($row) {
            $itemSet = $row->itemSet ?? $row->legacyItemSet;
            return [
                'id' => $row->id,
                'item_name' => optional(optional($itemSet)->item)->item_name ?? '-',
                'huid' => $row->huid ?? optional($itemSet)->HUID,
                'qr_code' => $row->qr_code ?? optional($itemSet)->qr_code,
                'gross_weight' => (float) $row->gross_weight,
                'other_weight' => (float) $row->other_weight,
                'net_weight' => (float) $row->net_weight,
                'total_amount' => (float) $row->total_amount,
                'status' => $row->status,
            ];
        });

        $billableItems = $approval->items->where('status', '!=', 'returned');
        $totals = [
            'gross_weight' => (float) $approval->items->sum('gross_weight'),
            'net_weight' => (float) $approval->items->sum('net_weight'),
            'amount_all' => (float) $approval->items->sum('total_amount'),
            'amount_billable' => (float) $billableItems->sum('total_amount'),
            'pending_count' => (int) $approval->items->where('status', 'pending')->count(),
            'sold_count' => (int) $approval->items->where('status', 'sold')->count(),
            'returned_count' => (int) $approval->items->where('status', 'returned')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $approval->id,
                'approval_no' => $approval->approval_no,
                'approval_date' => optional($approval->approval_date)->format('Y-m-d') ?? null,
                'status' => $approval->status,
                'customer' => $approval->customer,
                'totals' => $totals,
                'items' => $items,
            ]
        ]);
    }

    public function markSold(Request $request)
    {
        $companyId = $request->user()->company_id;
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'required|integer'
        ]);

        DB::beginTransaction();
        try {
            $approvalIds = [];
            foreach ($request->items as $id) {
                $item = ApprovalItem::whereHas('approval', function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                })->findOrFail($id);

                if ($item->status === 'pending') {
                    $item->update(['status' => 'sold']);
                }

                $approvalIds[] = $item->approval_id;
            }

            $this->updateApprovalStatus(array_values(array_unique($approvalIds)));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Items marked as sold'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function pendingItemsByCustomer(Request $request)
    {
        $companyId = $request->user()->company_id;
        $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
        ]);

        $items = ApprovalItem::with('itemSet.item', 'legacyItemSet.item')
            ->whereHas('approval', function ($q) use ($companyId, $request) {
                $q->where('company_id', $companyId)
                    ->where('customer_id', $request->customer_id);
            })
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get();

        $rows = $items->map(function ($row) {
            $itemSet = $row->itemSet ?? $row->legacyItemSet;
            $item = optional($itemSet)->item;
            return [
                'id' => $row->id,
                'approval_id' => $row->approval_id,
                'itemset_id' => $row->itemset_id ?? optional($itemSet)->id,
                'item_id' => $row->item_id ?? optional($itemSet)->item_id,
                'name' => optional($item)->item_name,
                'qr_code' => $row->qr_code ?? optional($itemSet)->qr_code,
                'huid' => $row->huid ?? optional($itemSet)->HUID,
                'gross_weight' => (float) $row->gross_weight,
                'other_weight' => (float) $row->other_weight,
                'net_weight' => (float) $row->net_weight,
                'purity' => (float) $row->purity,
                'waste_percent' => (float) $row->waste_percent,
                'net_purity' => (float) $row->net_purity,
                'fine_weight' => (float) $row->total_fine_weight,
                'metal_rate' => (float) $row->metal_rate,
                'metal_amount' => (float) $row->metal_amount,
                'labour_rate' => (float) $row->labour_rate,
                'labour_amount' => (float) $row->labour_amount,
                'other_amount' => (float) $row->other_amount,
                'total_amount' => (float) $row->total_amount,
                'status' => $row->status,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $rows
        ]);
    }

    public function returnItems(Request $request)
    {
        $companyId = $request->user()->company_id;
        $request->validate([
            'approval_id' => 'required|integer|exists:approval_headers,id',
            'items' => 'required|array|min:1',
            'items.*' => 'required|integer'
        ]);

        DB::beginTransaction();
        try {
            ApprovalHeader::where('company_id', $companyId)->findOrFail($request->approval_id);

            $hasItemsetIdColumn = Schema::hasColumn('sale_return_items', 'itemset_id');
            $hasProductIdColumn = Schema::hasColumn('sale_return_items', 'product_id');

            $return = SaleReturn::create([
                'company_id' => $companyId,
                'sale_id' => null,
                'source_type' => 'approval',
                'source_id' => $request->approval_id,
                'return_voucher_no' => 'SR' . time(),
                'return_date' => now(),
                'return_total' => 0,
            ]);

            $totalAmount = 0;
            $approvalIds = [];

            foreach ($request->items as $id) {
                $approvalItem = ApprovalItem::with('itemSet')
                    ->where('approval_id', $request->approval_id)
                    ->findOrFail($id);

                if ($approvalItem->status === 'returned') {
                    continue;
                }

                $itemSet = $approvalItem->itemSet;
                $amount = (float) $approvalItem->total_amount;

                $payload = [
                    'sale_return_id' => $return->id,
                    'sale_item_id' => null,
                    'return_amount' => $amount,
                ];

                if ($hasItemsetIdColumn && $itemSet) {
                    $payload['itemset_id'] = $itemSet->id;
                }

                if ($hasProductIdColumn) {
                    $payload['product_id'] = $approvalItem->item_id;
                }

                SaleReturnItem::create($payload);

                $totalAmount += $amount;
                $approvalItem->update(['status' => 'returned']);
                if ($itemSet) {
                    $itemSet->update(['is_sold' => 0]);
                }
                $approvalIds[] = $approvalItem->approval_id;
            }

            $return->update(['return_total' => $totalAmount]);
            $this->updateApprovalStatus(array_values(array_unique($approvalIds)));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Approval return voucher created successfully',
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

    public function pdf(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $approval = ApprovalHeader::with('customer', 'items.itemSet.item', 'items.legacyItemSet.item')
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $company = Company::find($companyId);

        $pdf = Pdf::loadView('company.approval.approval_pdf', compact('company', 'approval'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Approval-' . $approval->approval_no . '.pdf');
    }

    private function updateApprovalStatus(array $approvalIds): void
    {
        foreach ($approvalIds as $approvalId) {
            $total = ApprovalItem::where('approval_id', $approvalId)->count();
            $done = ApprovalItem::where('approval_id', $approvalId)
                ->whereIn('status', ['sold', 'returned'])
                ->count();

            $status = 'open';
            if ($total > 0 && $done === $total) {
                $status = 'closed';
            } elseif ($done > 0 && $done < $total) {
                $status = 'partial';
            }

            ApprovalHeader::where('id', $approvalId)->update(['status' => $status]);
        }
    }
}
