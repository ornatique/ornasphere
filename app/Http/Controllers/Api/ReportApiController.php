<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ItemSet;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $data = $this->barcodeHistoryRows($request, (int) $user->company_id, $code);

        return response()->json([
            'success' => true,
            'message' => 'Barcode history fetched successfully.',
            'count' => $data->count(),
            'data' => $data,
        ]);
    }

    public function barcodeHistorySuggest(Request $request)
    {
        $user = $request->user();
        $term = trim((string) $request->get('q', ''));
        $limit = max(1, min((int) $request->get('limit', 12), 50));

        if ($term === '') {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $like = '%' . $term . '%';
        $raw = DB::table('item_sets')
            ->leftJoin('items', 'items.id', '=', 'item_sets.item_id')
            ->where('item_sets.company_id', $user->company_id)
            ->where(function ($q) use ($like) {
                $q->where('item_sets.qr_code', 'like', $like)
                    ->orWhere('item_sets.barcode', 'like', $like)
                    ->orWhere('item_sets.HUID', 'like', $like);
            })
            ->orderByDesc('item_sets.id')
            ->limit($limit * 3)
            ->get([
                'item_sets.qr_code',
                'item_sets.barcode',
                'item_sets.HUID',
                'items.item_name',
            ]);

        $codes = collect();
        foreach ($raw as $row) {
            foreach (['qr_code', 'barcode', 'HUID'] as $field) {
                $val = trim((string) ($row->{$field} ?? ''));
                if ($val === '' || stripos($val, $term) === false) {
                    continue;
                }
                $codes->push([
                    'code' => $val,
                    'item_name' => $row->item_name ?: '',
                    'type' => $field,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $codes->unique('code')->values()->take($limit)->values(),
        ]);
    }

    public function barcodeHistoryExcel(Request $request): StreamedResponse
    {
        $user = $request->user();
        $code = trim((string) ($request->code ?? $request->barcode ?? $request->qr_code ?? $request->huid ?? ''));

        if ($code === '') {
            abort(422, 'Code is required.');
        }

        $rows = $this->barcodeHistoryRows($request, (int) $user->company_id, $code)
            ->map(fn($r) => is_array($r) ? $r : (array) $r)
            ->values();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Item',
                'Label Code',
                'Label Created At',
                'Label Printed At',
                'Approval History',
                'Sale History',
                'Return History',
                'Current Status',
            ]);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['item_name'] ?? '-',
                    $r['label_code'] ?? '-',
                    $r['label_created_at_fmt'] ?? '-',
                    $r['label_printed_at_fmt'] ?? '-',
                    $this->historyToText($r['approval_history'] ?? []),
                    $this->historyToText($r['sale_history'] ?? []),
                    $this->historyToText($r['return_history'] ?? []),
                    $r['current_status'] ?? '-',
                ]);
            }
            fclose($out);
        }, 'barcode_history_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function barcodeHistoryPdf(Request $request)
    {
        $user = $request->user();
        $code = trim((string) ($request->code ?? $request->barcode ?? $request->qr_code ?? $request->huid ?? ''));

        if ($code === '') {
            return response()->json([
                'success' => false,
                'message' => 'Code is required.',
            ], 422);
        }

        $company = Company::select('id', 'name')->find((int) $user->company_id);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }
        $rows = $this->barcodeHistoryRows($request, (int) $user->company_id, $code)
            ->map(fn($r) => is_array($r) ? $r : (array) $r)
            ->values();

        return Pdf::loadView('company.reports.pdf.barcode_history', compact('company', 'rows'))
            ->setPaper('a4', 'landscape')
            ->download('barcode_history_report.pdf');
    }

    private function barcodeHistoryRows(Request $request, int $companyId, string $code): Collection
    {
        $slug = trim((string) $request->get('slug', ''));

        $itemSets = ItemSet::query()
            ->leftJoin('items', 'items.id', '=', 'item_sets.item_id')
            ->where('item_sets.company_id', $companyId)
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

        return $itemSets->map(function ($set) use ($companyId, $slug) {
            $hasApprovalItemsetId = Schema::hasColumn('approval_items', 'itemset_id');
            $hasSaleReturnItemsetId = Schema::hasColumn('sale_return_items', 'itemset_id');

            $approvalHistory = DB::table('approval_items as ai')
                ->join('approval_headers as ah', 'ah.id', '=', 'ai.approval_id')
                ->where('ah.company_id', $companyId)
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
                        'id' => (int) $r->id,
                        'label' => $r->approval_no . ' (' . ( $r->approval_date ? Carbon::parse($r->approval_date)->format('d-m-Y') : '-') . ')' . (($r->status ?? '') !== '' ? ' [' . $r->status . ']' : ''),
                    ];
                })
                ->values();

            $saleHistory = DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->where('s.company_id', $companyId)
                ->where('si.itemset_id', $set->id)
                ->orderBy('s.sale_date')
                ->orderBy('s.id')
                ->get(['s.id', 's.voucher_no', 's.sale_date'])
                ->map(function ($r) {
                    return [
                        'id' => (int) $r->id,
                        'label' => $r->voucher_no . ' (' . ( $r->sale_date ? Carbon::parse($r->sale_date)->format('d-m-Y') : '-') . ')',
                    ];
                })
                ->values();

            $returnHistory = DB::table('sale_return_items as sri')
                ->join('sale_returns as sr', 'sr.id', '=', 'sri.sale_return_id')
                ->leftJoin('sale_items as si', 'si.id', '=', 'sri.sale_item_id')
                ->where('sr.company_id', $companyId)
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
                        'id' => (int) $r->id,
                        'label' => $r->return_voucher_no . ' (' . ( $r->return_date ? Carbon::parse($r->return_date)->format('d-m-Y') : '-') . ')',
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

            $buildUrl = function (string $type, int $id) use ($slug): ?string {
                if ($slug === '' || $id <= 0) {
                    return null;
                }
                $encryptedId = Crypt::encryptString((string) $id);
                return match ($type) {
                    'approval' => url("/company/{$slug}/approval/{$encryptedId}/view"),
                    'sale' => url("/company/{$slug}/sales/{$encryptedId}"),
                    'return' => url("/company/{$slug}/returns/{$encryptedId}/view"),
                    default => null,
                };
            };

            $approvalHistory = $approvalHistory->map(fn($h) => array_merge($h, ['url' => $buildUrl('approval', (int) ($h['id'] ?? 0))]))->values();
            $saleHistory = $saleHistory->map(fn($h) => array_merge($h, ['url' => $buildUrl('sale', (int) ($h['id'] ?? 0))]))->values();
            $returnHistory = $returnHistory->map(fn($h) => array_merge($h, ['url' => $buildUrl('return', (int) ($h['id'] ?? 0))]))->values();

            return [
                'itemset_id' => (int) $set->id,
                'item_name' => $set->item_name,
                'label_code' => $set->qr_code ?: $set->barcode,
                'barcode' => $set->barcode,
                'qr_code' => $set->qr_code,
                'huid' => $set->HUID,
                'label_created_at' => $set->created_at ? Carbon::parse($set->created_at)->format('Y-m-d H:i:s') : null,
                'label_printed_at' => $set->printed_at ? Carbon::parse($set->printed_at)->format('Y-m-d H:i:s') : null,
                'label_created_at_fmt' => $set->created_at ? Carbon::parse($set->created_at)->format('d-m-Y h:i A') : '-',
                'label_printed_at_fmt' => $set->printed_at ? Carbon::parse($set->printed_at)->format('d-m-Y h:i A') : '-',
                'approval_history' => $approvalHistory,
                'sale_history' => $saleHistory,
                'return_history' => $returnHistory,
                'current_status' => $currentStatus,
            ];
        })->values();
    }

    private function historyToText($history): string
    {
        if (!$history || count($history) === 0) {
            return '-';
        }

        return collect($history)
            ->map(fn($row) => (string) ($row['label'] ?? '-'))
            ->filter(fn($label) => $label !== '')
            ->implode(' | ');
    }
}
