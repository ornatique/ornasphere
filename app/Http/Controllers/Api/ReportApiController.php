<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalHeader;
use App\Models\Company;
use App\Models\ItemSet;
use App\Models\Sale;
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
    public function purchaseReceiverSummary(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->purchaseReceiverSummaryBaseQuery($request, $companyId)
            ->orderByDesc('sr.id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'return_voucher_no' => $row->return_voucher_no,
                    'return_date' => $row->return_date ? Carbon::parse($row->return_date)->format('Y-m-d') : null,
                    'return_date_fmt' => $row->return_date ? Carbon::parse($row->return_date)->format('d-m-Y') : '-',
                    'source_type' => (string) ($row->source_type ?: 'sale'),
                    'customer_name' => $row->customer_name ?: '-',
                    'created_by' => $row->created_by ?: '-',
                    'remarks' => $row->remarks ?? '-',
                    'qty_pcs' => (int) ($row->total_qty ?? 0),
                    'gross_weight' => (float) ($row->total_gross_weight ?? 0),
                    'net_weight' => (float) ($row->total_net_weight ?? 0),
                    'fine_weight' => (float) ($row->total_fine_weight ?? 0),
                    'metal_amount' => (float) ($row->total_metal_amount ?? 0),
                    'labour_amount' => (float) ($row->total_labour_amount ?? 0),
                    'other_amount' => (float) ($row->total_other_amount ?? 0),
                    'return_total' => (float) ($row->return_total ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Purchase receiver summary fetched successfully.',
            'count' => $rows->count(),
            'totals' => $this->purchaseReceiverSummaryTotals($request, $companyId),
            'data' => $rows,
        ]);
    }

    public function purchaseReceiverSummaryExcel(Request $request): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->purchaseReceiverSummaryBaseQuery($request, $companyId)->orderByDesc('sr.id')->get();
        $totals = $this->purchaseReceiverSummaryTotals($request, $companyId);

        return response()->streamDownload(function () use ($rows, $totals) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Voucher No', 'Date', 'Customer', 'Source', 'Qty', 'Gross Wt', 'Net Wt', 'Fine Wt', 'Metal Amt', 'Labour Amt', 'Other Amt', 'Total', 'Remarks', 'Created By']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->return_voucher_no,
                    $r->return_date ? Carbon::parse($r->return_date)->format('d-m-Y') : '-',
                    $r->customer_name ?: '-',
                    ucfirst((string) ($r->source_type ?: 'sale')),
                    (int) ($r->total_qty ?? 0),
                    number_format((float) ($r->total_gross_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->total_net_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->total_fine_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->total_metal_amount ?? 0), 2, '.', ''),
                    number_format((float) ($r->total_labour_amount ?? 0), 2, '.', ''),
                    number_format((float) ($r->total_other_amount ?? 0), 2, '.', ''),
                    number_format((float) ($r->return_total ?? 0), 2, '.', ''),
                    $r->remarks ?? '-',
                    $r->created_by ?? '-',
                ]);
            }
            fputcsv($out, [
                'TOTAL', '', '', '',
                (int) ($totals['qty_pcs'] ?? 0),
                number_format((float) ($totals['gross_weight'] ?? 0), 3, '.', ''),
                number_format((float) ($totals['net_weight'] ?? 0), 3, '.', ''),
                number_format((float) ($totals['fine_weight'] ?? 0), 3, '.', ''),
                number_format((float) ($totals['metal_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($totals['labour_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($totals['other_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($totals['return_total'] ?? 0), 2, '.', ''),
                '', '',
            ]);
            fclose($out);
        }, 'purchase_receiver_summary_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function purchaseReceiverSummaryPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::select('id', 'name')->find($companyId);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }

        $rows = $this->purchaseReceiverSummaryBaseQuery($request, $companyId)->orderByDesc('sr.id')->get();
        $totals = $this->purchaseReceiverSummaryTotals($request, $companyId);

        return Pdf::loadView('company.reports.pdf.purchase_receiver_summary', compact('company', 'rows', 'totals'))
            ->setPaper('a4', 'portrait')
            ->download('purchase_receiver_summary_report.pdf');
    }

    public function stockPosition(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->stockPositionBaseQuery($request, $companyId)
            ->get()
            ->map(function ($row) {
                return [
                    'item_id' => (int) $row->item_id,
                    'item_name' => $row->item_name,
                    'qty_pcs' => (int) ($row->qty_pcs ?? 0),
                    'gross_weight' => (float) ($row->gross_weight ?? 0),
                    'net_weight' => (float) ($row->net_weight ?? 0),
                    'labour_amount' => (float) ($row->labour_amount ?? 0),
                    'other_amount' => (float) ($row->other_amount ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Stock position fetched successfully.',
            'count' => $rows->count(),
            'data' => $rows,
        ]);
    }

    public function stockPositionExcel(Request $request): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->stockPositionBaseQuery($request, $companyId)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Item', 'Qty Pcs', 'Gross Wt', 'Net Wt', 'Labour Amt', 'Other Amt']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->item_name,
                    (int) ($r->qty_pcs ?? 0),
                    number_format((float) ($r->gross_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->net_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->labour_amount ?? 0), 2, '.', ''),
                    number_format((float) ($r->other_amount ?? 0), 2, '.', ''),
                ]);
            }
            fclose($out);
        }, 'stock_position_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function stockPositionPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::select('id', 'name')->find($companyId);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }
        $rows = $this->stockPositionBaseQuery($request, $companyId)->get();

        return Pdf::loadView('company.reports.pdf.stock_position', compact('company', 'rows'))
            ->setPaper('a4', 'portrait')
            ->download('stock_position_report.pdf');
    }

    public function approvalOutstanding(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->approvalOutstandingBaseQuery($request, $companyId)
            ->latest('approval_date')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'approval_no' => $row->approval_no,
                    'approval_date' => optional($row->approval_date)?->format('Y-m-d'),
                    'approval_date_fmt' => optional($row->approval_date)?->format('d-m-Y') ?? '-',
                    'customer_name' => optional($row->customer)->name ?? '-',
                    'status' => $row->status,
                    'remarks' => $row->remarks ?? '-',
                    'created_by' => optional($row->creator)->name ?? '-',
                    'pending_items' => (int) ($row->pending_items_count ?? 0),
                    'pending_net_weight' => (float) ($row->pending_net_weight ?? 0),
                    'pending_total_amount' => (float) ($row->pending_total_amount ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Approval outstanding fetched successfully.',
            'count' => $rows->count(),
            'data' => $rows,
        ]);
    }

    public function approvalOutstandingExcel(Request $request): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->approvalOutstandingBaseQuery($request, $companyId)->latest('approval_date')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Approval No', 'Date', 'Customer', 'Status', 'Pending Pcs', 'Pending Net Wt', 'Pending Amount', 'Remarks', 'Created By']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->approval_no,
                    optional($r->approval_date)?->format('d-m-Y'),
                    optional($r->customer)->name ?? '-',
                    $r->status,
                    (int) ($r->pending_items_count ?? 0),
                    number_format((float) ($r->pending_net_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->pending_total_amount ?? 0), 2, '.', ''),
                    $r->remarks ?? '-',
                    optional($r->creator)->name ?? '-',
                ]);
            }
            fclose($out);
        }, 'approval_outstanding_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function approvalOutstandingPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::select('id', 'name')->find($companyId);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }
        $rows = $this->approvalOutstandingBaseQuery($request, $companyId)->latest('approval_date')->get();

        return Pdf::loadView('company.reports.pdf.approval_outstanding', compact('company', 'rows'))
            ->setPaper('a4', 'portrait')
            ->download('approval_outstanding_report.pdf');
    }

    public function salesSummary(Request $request)
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;

        $rows = $this->salesSummaryBaseQuery($request, $companyId)
            ->latest()
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => (int) $sale->id,
                    'voucher_no' => $sale->voucher_no,
                    'sale_date' => optional($sale->sale_date)?->format('Y-m-d'),
                    'sale_date_fmt' => optional($sale->sale_date)?->format('d-m-Y'),
                    'customer_name' => optional($sale->customer)->name ?? '-',
                    'remarks' => $sale->remarks ?? '-',
                    'created_by' => optional($sale->creator)->name ?? '-',
                    'qty_pcs' => (int) ($sale->total_qty ?? 0),
                    'gross_weight' => (float) ($sale->total_gross_weight ?? 0),
                    'net_weight' => (float) ($sale->total_net_weight ?? 0),
                    'fine_weight' => (float) ($sale->total_fine_weight ?? 0),
                    'metal_amount' => (float) ($sale->total_metal_amount ?? 0),
                    'labour_amount' => (float) ($sale->total_labour_amount ?? 0),
                    'other_amount' => (float) ($sale->total_other_amount ?? 0),
                    'net_total' => (float) ($sale->net_total ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Sales summary fetched successfully.',
            'count' => $rows->count(),
            'totals' => $this->salesSummaryTotals($request, $companyId),
            'data' => $rows,
        ]);
    }

    public function salesSummaryExcel(Request $request): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->salesSummaryBaseQuery($request, $companyId)->latest()->get();
        $totals = $this->salesSummaryTotals($request, $companyId);

        return response()->streamDownload(function () use ($rows, $totals) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Voucher No', 'Date', 'Customer', 'Qty', 'Gross Wt', 'Net Wt', 'Fine Wt', 'Metal Amt', 'Labour Amt', 'Other Amt', 'Total', 'Remarks', 'Created By']);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->voucher_no,
                    optional($r->sale_date)?->format('d-m-Y'),
                    optional($r->customer)->name ?? '-',
                    (int) ($r->total_qty ?? 0),
                    number_format((float) ($r->total_gross_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->total_net_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->total_fine_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->total_metal_amount ?? 0), 2, '.', ''),
                    number_format((float) ($r->total_labour_amount ?? 0), 2, '.', ''),
                    number_format((float) ($r->total_other_amount ?? 0), 2, '.', ''),
                    number_format((float) ($r->net_total ?? 0), 2, '.', ''),
                    $r->remarks ?? '-',
                    optional($r->creator)->name ?? '-',
                ]);
            }

            fputcsv($out, [
                'TOTAL',
                '',
                '',
                (int) ($totals['qty_pcs'] ?? 0),
                number_format((float) ($totals['gross_weight'] ?? 0), 3, '.', ''),
                number_format((float) ($totals['net_weight'] ?? 0), 3, '.', ''),
                number_format((float) ($totals['fine_weight'] ?? 0), 3, '.', ''),
                number_format((float) ($totals['metal_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($totals['labour_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($totals['other_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($totals['net_total'] ?? 0), 2, '.', ''),
                '',
                '',
            ]);

            fclose($out);
        }, 'sales_summary_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function salesSummaryPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::select('id', 'name')->find($companyId);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }

        $rows = $this->salesSummaryBaseQuery($request, $companyId)->latest()->get();
        $totals = $this->salesSummaryTotals($request, $companyId);

        return Pdf::loadView('company.reports.pdf.sales_summary', compact('company', 'rows', 'totals'))
            ->setPaper('a4', 'portrait')
            ->download('sales_summary_report.pdf');
    }

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
        if ($slug === '') {
            $slug = (string) (Company::where('id', $companyId)->value('slug') ?? '');
        }

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

            $buildApiUrl = function (string $type, int $id): ?string {
                if ($id <= 0) {
                    return null;
                }

                return match ($type) {
                    'approval' => url("/api/approvals/{$id}"),
                    'sale' => url("/api/sales/{$id}"),
                    'return' => url("/api/returns/{$id}"),
                    default => null,
                };
            };

            $approvalHistory = $approvalHistory->map(fn($h) => array_merge($h, [
                'url' => $buildUrl('approval', (int) ($h['id'] ?? 0)),
                'api_url' => $buildApiUrl('approval', (int) ($h['id'] ?? 0)),
                'type' => 'approval',
            ]))->values();
            $saleHistory = $saleHistory->map(fn($h) => array_merge($h, [
                'url' => $buildUrl('sale', (int) ($h['id'] ?? 0)),
                'api_url' => $buildApiUrl('sale', (int) ($h['id'] ?? 0)),
                'type' => 'sale',
            ]))->values();
            $returnHistory = $returnHistory->map(fn($h) => array_merge($h, [
                'url' => $buildUrl('return', (int) ($h['id'] ?? 0)),
                'api_url' => $buildApiUrl('return', (int) ($h['id'] ?? 0)),
                'type' => 'return',
            ]))->values();

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

    private function salesSummaryBaseQuery(Request $request, int $companyId)
    {
        $query = Sale::with(['customer', 'creator'])
            ->withSum('saleItems as total_qty', 'qty')
            ->withSum('saleItems as total_gross_weight', 'gross_weight')
            ->withSum('saleItems as total_net_weight', 'net_weight')
            ->withSum('saleItems as total_fine_weight', 'fine_weight')
            ->withSum('saleItems as total_metal_amount', 'metal_amount')
            ->withSum('saleItems as total_labour_amount', 'labour_amount')
            ->withSum('saleItems as total_other_amount', 'other_amount')
            ->where('company_id', $companyId);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->customer_id);
        }
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('sale_date', [$request->from_date, $request->to_date]);
        } elseif ($request->filled('from_date')) {
            $query->whereDate('sale_date', '>=', $request->from_date);
        } elseif ($request->filled('to_date')) {
            $query->whereDate('sale_date', '<=', $request->to_date);
        }

        return $query;
    }

    private function salesSummaryTotals(Request $request, int $companyId): array
    {
        $applySalesFilters = function ($query) use ($request, $companyId) {
            $query->where('sales.company_id', $companyId);

            if ($request->filled('customer_id')) {
                $query->where('sales.customer_id', (int) $request->customer_id);
            }
            if ($request->filled('from_date') && $request->filled('to_date')) {
                $query->whereBetween('sales.sale_date', [$request->from_date, $request->to_date]);
            } elseif ($request->filled('from_date')) {
                $query->whereDate('sales.sale_date', '>=', $request->from_date);
            } elseif ($request->filled('to_date')) {
                $query->whereDate('sales.sale_date', '<=', $request->to_date);
            }
        };

        $weightAndAmountTotals = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where($applySalesFilters)
            ->selectRaw('
                COALESCE(SUM(sale_items.qty), 0) as qty_pcs,
                COALESCE(SUM(sale_items.gross_weight), 0) as gross_weight,
                COALESCE(SUM(sale_items.net_weight), 0) as net_weight,
                COALESCE(SUM(sale_items.fine_weight), 0) as fine_weight,
                COALESCE(SUM(sale_items.metal_amount), 0) as metal_amount,
                COALESCE(SUM(sale_items.labour_amount), 0) as labour_amount,
                COALESCE(SUM(sale_items.other_amount), 0) as other_amount
            ')
            ->first();

        $netTotal = DB::table('sales')
            ->where($applySalesFilters)
            ->selectRaw('COALESCE(SUM(sales.net_total), 0) as net_total')
            ->value('net_total');

        return [
            'qty_pcs' => (int) ($weightAndAmountTotals->qty_pcs ?? 0),
            'gross_weight' => (float) ($weightAndAmountTotals->gross_weight ?? 0),
            'net_weight' => (float) ($weightAndAmountTotals->net_weight ?? 0),
            'fine_weight' => (float) ($weightAndAmountTotals->fine_weight ?? 0),
            'metal_amount' => (float) ($weightAndAmountTotals->metal_amount ?? 0),
            'labour_amount' => (float) ($weightAndAmountTotals->labour_amount ?? 0),
            'other_amount' => (float) ($weightAndAmountTotals->other_amount ?? 0),
            'net_total' => (float) ($netTotal ?? 0),
        ];
    }

    private function purchaseReceiverSummaryBaseQuery(Request $request, int $companyId)
    {
        $hasReturnItemsetId = Schema::hasColumn('sale_return_items', 'itemset_id');

        $query = DB::table('sale_returns as sr')
            ->leftJoin('sale_return_items as sri', 'sri.sale_return_id', '=', 'sr.id')
            ->leftJoin('sale_items as si', 'si.id', '=', 'sri.sale_item_id')
            ->leftJoin('sales as s', 's.id', '=', 'sr.sale_id')
            ->leftJoin('customers as sc', 'sc.id', '=', 's.customer_id')
            ->leftJoin('users as su', 'su.id', '=', 's.employee_id')
            ->leftJoin('approval_headers as ah', function ($join) {
                $join->on('ah.id', '=', 'sr.source_id')
                    ->whereIn('sr.source_type', ['approval', 'mixed']);
            })
            ->leftJoin('customers as ac', 'ac.id', '=', 'ah.customer_id')
            ->leftJoin('users as au', 'au.id', '=', 'ah.employee_id')
            ->where('sr.company_id', $companyId)
            ->when($request->filled('customer_id'), function ($q) use ($request) {
                $customerId = (int) $request->customer_id;
                $q->where(function ($inner) use ($customerId) {
                    $inner->where('s.customer_id', $customerId)
                        ->orWhere('ah.customer_id', $customerId);
                });
            })
            ->when($request->filled('from_date') && $request->filled('to_date'), function ($q) use ($request) {
                $q->whereBetween('sr.return_date', [$request->from_date, $request->to_date]);
            })
            ->when($request->filled('from_date') && !$request->filled('to_date'), function ($q) use ($request) {
                $q->whereDate('sr.return_date', '>=', $request->from_date);
            })
            ->when(!$request->filled('from_date') && $request->filled('to_date'), function ($q) use ($request) {
                $q->whereDate('sr.return_date', '<=', $request->to_date);
            })
            ->groupBy(
                'sr.id',
                'sr.return_voucher_no',
                'sr.return_date',
                'sr.return_total',
                'sr.remarks',
                'sr.source_type',
                'sr.created_at',
                'sc.name',
                'ac.name',
                'su.name',
                'au.name'
            );

        if ($hasReturnItemsetId) {
            $query->leftJoin('item_sets as iset', 'iset.id', '=', 'sri.itemset_id');
        }

        $grossWeightExpr = $hasReturnItemsetId ? 'COALESCE(si.gross_weight, iset.gross_weight, 0)' : 'COALESCE(si.gross_weight, 0)';
        $netWeightExpr = $hasReturnItemsetId ? 'COALESCE(si.net_weight, iset.net_weight, 0)' : 'COALESCE(si.net_weight, 0)';

        $query->selectRaw("
                sr.id,
                sr.return_voucher_no,
                sr.return_date,
                sr.return_total,
                sr.remarks,
                sr.source_type,
                sr.created_at,
                COALESCE(sc.name, ac.name, '-') as customer_name,
                COALESCE(su.name, au.name, '-') as created_by,
                COUNT(sri.id) as total_qty,
                COALESCE(SUM({$grossWeightExpr}), 0) as total_gross_weight,
                COALESCE(SUM({$netWeightExpr}), 0) as total_net_weight,
                COALESCE(SUM(COALESCE(si.fine_weight, 0)), 0) as total_fine_weight,
                COALESCE(SUM(COALESCE(si.metal_amount, 0)), 0) as total_metal_amount,
                COALESCE(SUM(COALESCE(si.labour_amount, 0)), 0) as total_labour_amount,
                COALESCE(SUM(COALESCE(si.other_amount, 0)), 0) as total_other_amount
            ");

        return $query;
    }

    private function purchaseReceiverSummaryTotals(Request $request, int $companyId): array
    {
        $rows = $this->purchaseReceiverSummaryBaseQuery($request, $companyId)->get();

        return [
            'qty_pcs' => (int) $rows->sum(fn($r) => (int) ($r->total_qty ?? 0)),
            'gross_weight' => (float) $rows->sum(fn($r) => (float) ($r->total_gross_weight ?? 0)),
            'net_weight' => (float) $rows->sum(fn($r) => (float) ($r->total_net_weight ?? 0)),
            'fine_weight' => (float) $rows->sum(fn($r) => (float) ($r->total_fine_weight ?? 0)),
            'metal_amount' => (float) $rows->sum(fn($r) => (float) ($r->total_metal_amount ?? 0)),
            'labour_amount' => (float) $rows->sum(fn($r) => (float) ($r->total_labour_amount ?? 0)),
            'other_amount' => (float) $rows->sum(fn($r) => (float) ($r->total_other_amount ?? 0)),
            'return_total' => (float) $rows->sum(fn($r) => (float) ($r->return_total ?? 0)),
        ];
    }

    private function stockPositionBaseQuery(Request $request, int $companyId)
    {
        return ItemSet::query()
            ->join('items', 'items.id', '=', 'item_sets.item_id')
            ->where('item_sets.company_id', $companyId)
            ->where('item_sets.is_final', 1)
            ->where('item_sets.is_sold', 0)
            ->when($request->filled('item_id'), function ($q) use ($request) {
                $q->where('item_sets.item_id', (int) $request->item_id);
            })
            ->select([
                'item_sets.item_id',
                'items.item_name',
                DB::raw('COUNT(item_sets.id) as qty_pcs'),
                DB::raw('SUM(COALESCE(item_sets.gross_weight,0)) as gross_weight'),
                DB::raw('SUM(COALESCE(item_sets.net_weight,0)) as net_weight'),
                DB::raw('SUM(COALESCE(item_sets.sale_labour_amount,0)) as labour_amount'),
                DB::raw('SUM(COALESCE(item_sets.sale_other,0)) as other_amount'),
            ])
            ->groupBy('item_sets.item_id', 'items.item_name');
    }

    private function approvalOutstandingBaseQuery(Request $request, int $companyId)
    {
        $query = ApprovalHeader::with(['customer', 'creator'])
            ->withCount([
                'items as pending_items_count' => function ($q) {
                    $q->where('status', 'pending');
                }
            ])
            ->withSum([
                'items as pending_net_weight' => function ($q) {
                    $q->where('status', 'pending');
                }
            ], 'net_weight')
            ->withSum([
                'items as pending_total_amount' => function ($q) {
                    $q->where('status', 'pending');
                }
            ], 'total_amount')
            ->where('company_id', $companyId)
            ->whereIn('status', ['open', 'partial']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->customer_id);
        }
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('approval_date', [$request->from_date, $request->to_date]);
        } elseif ($request->filled('from_date')) {
            $query->whereDate('approval_date', '>=', $request->from_date);
        } elseif ($request->filled('to_date')) {
            $query->whereDate('approval_date', '<=', $request->to_date);
        }

        return $query;
    }
}
