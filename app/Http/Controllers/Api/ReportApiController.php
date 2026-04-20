<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemSet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportApiController extends Controller
{
    public function barcodeHistory(Request $request)
    {
        $user = $request->user();
        $code = trim((string) ($request->code ?? $request->barcode ?? $request->qr_code ?? $request->huid ?? ''));

        if ($code === '') {
            return response()->json([
                'success' => false,
                'message' => 'Code is required.',
                'data' => [],
            ], 422);
        }

        $itemSets = ItemSet::query()
            ->leftJoin('items', 'items.id', '=', 'item_sets.item_id')
            ->where('item_sets.company_id', $user->company_id)
            ->where(function ($q) use ($code) {
                $q->where('item_sets.qr_code', $code)
                    ->orWhere('item_sets.barcode', $code)
                    ->orWhere('item_sets.HUID', $code);
            })
            ->select([
                'item_sets.*',
                'items.item_name',
            ])
            ->orderByDesc('item_sets.id')
            ->get();

        $data = $itemSets->map(function ($set) use ($user) {
            $hasApprovalItemsetId = Schema::hasColumn('approval_items', 'itemset_id');
            $hasSaleReturnItemsetId = Schema::hasColumn('sale_return_items', 'itemset_id');

            $approvalHistory = DB::table('approval_items as ai')
                ->join('approval_headers as ah', 'ah.id', '=', 'ai.approval_id')
                ->where('ah.company_id', $user->company_id)
                ->where(function ($q) use ($set, $hasApprovalItemsetId) {
                    if ($hasApprovalItemsetId) {
                        $q->where('ai.itemset_id', $set->id);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                    if (!empty($set->qr_code)) {
                        $q->orWhere('ai.qr_code', $set->qr_code);
                    }
                })
                ->orderBy('ah.approval_date')
                ->orderBy('ah.id')
                ->get(['ah.id', 'ah.approval_no', 'ah.approval_date', 'ai.status'])
                ->map(function ($r) {
                    return [
                        'approval_id' => (int) $r->id,
                        'approval_no' => $r->approval_no,
                        'approval_date' => $r->approval_date ? Carbon::parse($r->approval_date)->format('Y-m-d') : null,
                        'status' => $r->status ?: null,
                    ];
                })
                ->values();

            $saleHistory = DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->where('s.company_id', $user->company_id)
                ->where('si.itemset_id', $set->id)
                ->orderBy('s.sale_date')
                ->orderBy('s.id')
                ->get(['s.id', 's.voucher_no', 's.sale_date'])
                ->map(function ($r) {
                    return [
                        'sale_id' => (int) $r->id,
                        'voucher_no' => $r->voucher_no,
                        'sale_date' => $r->sale_date ? Carbon::parse($r->sale_date)->format('Y-m-d') : null,
                    ];
                })
                ->values();

            $returnHistory = DB::table('sale_return_items as sri')
                ->join('sale_returns as sr', 'sr.id', '=', 'sri.sale_return_id')
                ->leftJoin('sale_items as si', 'si.id', '=', 'sri.sale_item_id')
                ->where('sr.company_id', $user->company_id)
                ->where(function ($q) use ($set, $hasSaleReturnItemsetId) {
                    if ($hasSaleReturnItemsetId) {
                        $q->where('sri.itemset_id', $set->id);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                    $q->orWhere('si.itemset_id', $set->id);
                })
                ->orderBy('sr.return_date')
                ->orderBy('sr.id')
                ->get(['sr.id', 'sr.return_voucher_no', 'sr.return_date'])
                ->map(function ($r) {
                    return [
                        'return_id' => (int) $r->id,
                        'return_voucher_no' => $r->return_voucher_no,
                        'return_date' => $r->return_date ? Carbon::parse($r->return_date)->format('Y-m-d') : null,
                    ];
                })
                ->values();

            $currentStatus = 'in_stock';
            if ($returnHistory->isNotEmpty()) {
                $currentStatus = 'returned';
            } elseif ($saleHistory->isNotEmpty() || (int) ($set->is_sold ?? 0) === 1) {
                $currentStatus = 'sold';
            } elseif ($approvalHistory->isNotEmpty()) {
                $currentStatus = 'approval';
            }

            return [
                'itemset_id' => (int) $set->id,
                'item_name' => $set->item_name,
                'label_code' => $set->qr_code ?: $set->barcode,
                'barcode' => $set->barcode,
                'qr_code' => $set->qr_code,
                'huid' => $set->HUID,
                'label_created_at' => $set->created_at ? Carbon::parse($set->created_at)->format('Y-m-d H:i:s') : null,
                'label_printed_at' => $set->printed_at ? Carbon::parse($set->printed_at)->format('Y-m-d H:i:s') : null,
                'approval_history' => $approvalHistory,
                'sale_history' => $saleHistory,
                'return_history' => $returnHistory,
                'current_status' => $currentStatus,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Barcode history fetched successfully.',
            'count' => $data->count(),
            'data' => $data,
        ]);
    }
}
