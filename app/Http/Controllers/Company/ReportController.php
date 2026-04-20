<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\ApprovalHeader;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ItemSet;
use App\Models\Sale;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class ReportController extends Controller
{
    public function salesSummary(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            return DataTables::of($this->salesSummaryBaseQuery($company, $request)->latest())
                ->addIndexColumn()
                ->addColumn('customer_name', fn($row) => optional($row->customer)->name ?? '-')
                ->addColumn('created_by', fn($row) => optional($row->creator)->name ?? '-')
                ->editColumn('sale_date', fn($row) => optional($row->sale_date)?->format('d-m-Y') ?? '-')
                ->addColumn('qty_pcs', fn($row) => (int) ($row->total_qty ?? 0))
                ->addColumn('gross_weight', fn($row) => number_format((float) ($row->total_gross_weight ?? 0), 3))
                ->addColumn('net_weight', fn($row) => number_format((float) ($row->total_net_weight ?? 0), 3))
                ->addColumn('fine_weight', fn($row) => number_format((float) ($row->total_fine_weight ?? 0), 3))
                ->addColumn('metal_amount', fn($row) => number_format((float) ($row->total_metal_amount ?? 0), 2))
                ->addColumn('labour_amount', fn($row) => number_format((float) ($row->total_labour_amount ?? 0), 2))
                ->addColumn('other_amount', fn($row) => number_format((float) ($row->total_other_amount ?? 0), 2))
                ->editColumn('net_total', fn($row) => number_format((float) ($row->net_total ?? 0), 2))
                ->make(true);
        }

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('company.reports.sales_summary', compact('company', 'customers'));
    }

    public function salesSummaryExcel(Request $request, $slug): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->salesSummaryBaseQuery($company, $request)->latest()->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Voucher No', 'Date', 'Customer', 'Qty', 'Gross Wt', 'Net Wt', 'Fine Wt', 'Metal Amt', 'Labour Amt', 'Other Amt', 'Total', 'Created By']);
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
                    optional($r->creator)->name ?? '-',
                ]);
            }
            fclose($out);
        }, 'sales_summary_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function salesSummaryPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->salesSummaryBaseQuery($company, $request)->latest()->get();

        return Pdf::loadView('company.reports.pdf.sales_summary', compact('company', 'rows'))
            ->setPaper('a4', 'landscape')
            ->download('sales_summary_report.pdf');
    }

    public function stockPosition(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            return DataTables::of($this->stockPositionBaseQuery($company, $request))
                ->addIndexColumn()
                ->editColumn('qty_pcs', fn($row) => (int) ($row->qty_pcs ?? 0))
                ->editColumn('gross_weight', fn($row) => number_format((float) ($row->gross_weight ?? 0), 3))
                ->editColumn('net_weight', fn($row) => number_format((float) ($row->net_weight ?? 0), 3))
                ->editColumn('labour_amount', fn($row) => number_format((float) ($row->labour_amount ?? 0), 2))
                ->editColumn('other_amount', fn($row) => number_format((float) ($row->other_amount ?? 0), 2))
                ->make(true);
        }

        $items = DB::table('items')
            ->where('company_id', $company->id)
            ->orderBy('item_name')
            ->select('id', 'item_name')
            ->get();

        return view('company.reports.stock_position', compact('company', 'items'));
    }

    public function stockPositionExcel(Request $request, $slug): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->stockPositionBaseQuery($company, $request)->get();

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

    public function stockPositionPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->stockPositionBaseQuery($company, $request)->get();

        return Pdf::loadView('company.reports.pdf.stock_position', compact('company', 'rows'))
            ->setPaper('a4', 'portrait')
            ->download('stock_position_report.pdf');
    }

    public function approvalOutstanding(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            return DataTables::of($this->approvalOutstandingBaseQuery($company, $request)->latest('approval_date'))
                ->addIndexColumn()
                ->addColumn('customer_name', fn($row) => optional($row->customer)->name ?? '-')
                ->addColumn('approval_date_fmt', fn($row) => optional($row->approval_date)?->format('d-m-Y') ?? '-')
                ->addColumn('pending_items', fn($row) => (int) ($row->pending_items_count ?? 0))
                ->addColumn('pending_net_weight_fmt', fn($row) => number_format((float) ($row->pending_net_weight ?? 0), 3))
                ->addColumn('pending_total_amount_fmt', fn($row) => number_format((float) ($row->pending_total_amount ?? 0), 2))
                ->make(true);
        }

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('company.reports.approval_outstanding', compact('company', 'customers'));
    }

    public function approvalOutstandingExcel(Request $request, $slug): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->approvalOutstandingBaseQuery($company, $request)->latest('approval_date')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Approval No', 'Date', 'Customer', 'Status', 'Pending Pcs', 'Pending Net Wt', 'Pending Amount']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->approval_no,
                    optional($r->approval_date)?->format('d-m-Y'),
                    optional($r->customer)->name ?? '-',
                    $r->status,
                    (int) ($r->pending_items_count ?? 0),
                    number_format((float) ($r->pending_net_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->pending_total_amount ?? 0), 2, '.', ''),
                ]);
            }
            fclose($out);
        }, 'approval_outstanding_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function approvalOutstandingPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->approvalOutstandingBaseQuery($company, $request)->latest('approval_date')->get();

        return Pdf::loadView('company.reports.pdf.approval_outstanding', compact('company', 'rows'))
            ->setPaper('a4', 'portrait')
            ->download('approval_outstanding_report.pdf');
    }

    public function barcodeHistory(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $rows = $this->barcodeHistoryRows($company, $request);

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('item_name', fn($row) => $row['item_name'] ?: '-')
                ->addColumn('label_code', fn($row) => $row['label_code'] ?: '-')
                ->addColumn('label_created_at_fmt', fn($row) => $row['label_created_at_fmt'] ?: '-')
                ->addColumn('label_printed_at_fmt', fn($row) => $row['label_printed_at_fmt'] ?: '-')
                ->addColumn('approval_history_html', function ($row) {
                    if (empty($row['approval_history'])) {
                        return '<span class="text-muted">-</span>';
                    }
                    return implode('<br>', $row['approval_history']);
                })
                ->addColumn('sale_history_html', function ($row) {
                    if (empty($row['sale_history'])) {
                        return '<span class="text-muted">-</span>';
                    }
                    return implode('<br>', $row['sale_history']);
                })
                ->addColumn('return_history_html', function ($row) {
                    if (empty($row['return_history'])) {
                        return '<span class="text-muted">-</span>';
                    }
                    return implode('<br>', $row['return_history']);
                })
                ->addColumn('current_status', fn($row) => $row['current_status'])
                ->rawColumns(['approval_history_html', 'sale_history_html', 'return_history_html'])
                ->make(true);
        }

        return view('company.reports.barcode_history', compact('company'));
    }

    public function barcodeHistorySuggest(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
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
            ->where('item_sets.company_id', $company->id)
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

        $codes = $codes
            ->unique('code')
            ->values()
            ->take($limit);

        return response()->json([
            'success' => true,
            'data' => $codes,
        ]);
    }

    public function barcodeHistoryExcel(Request $request, $slug): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->barcodeHistoryRows($company, $request);

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
                    !empty($r['approval_history']) ? implode(' | ', $r['approval_history']) : '-',
                    !empty($r['sale_history']) ? implode(' | ', $r['sale_history']) : '-',
                    !empty($r['return_history']) ? implode(' | ', $r['return_history']) : '-',
                    $r['current_status'] ?? '-',
                ]);
            }
            fclose($out);
        }, 'barcode_history_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function barcodeHistoryPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->barcodeHistoryRows($company, $request);

        return Pdf::loadView('company.reports.pdf.barcode_history', compact('company', 'rows'))
            ->setPaper('a4', 'landscape')
            ->download('barcode_history_report.pdf');
    }

    private function salesSummaryBaseQuery(Company $company, Request $request)
    {
        $query = Sale::with(['customer', 'creator'])
            ->withSum('saleItems as total_qty', 'qty')
            ->withSum('saleItems as total_gross_weight', 'gross_weight')
            ->withSum('saleItems as total_net_weight', 'net_weight')
            ->withSum('saleItems as total_fine_weight', 'fine_weight')
            ->withSum('saleItems as total_metal_amount', 'metal_amount')
            ->withSum('saleItems as total_labour_amount', 'labour_amount')
            ->withSum('saleItems as total_other_amount', 'other_amount')
            ->where('company_id', $company->id);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->customer_id);
        }
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('sale_date', [$request->from_date, $request->to_date]);
        }

        return $query;
    }

    private function stockPositionBaseQuery(Company $company, Request $request)
    {
        return ItemSet::query()
            ->join('items', 'items.id', '=', 'item_sets.item_id')
            ->where('item_sets.company_id', $company->id)
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

    private function approvalOutstandingBaseQuery(Company $company, Request $request)
    {
        $query = ApprovalHeader::with('customer')
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
            ->where('company_id', $company->id)
            ->whereIn('status', ['open', 'partial']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->customer_id);
        }
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('approval_date', [$request->from_date, $request->to_date]);
        }

        return $query;
    }

    private function barcodeHistoryRows(Company $company, Request $request): Collection
    {
        $code = trim((string) ($request->code ?? ''));
        if ($code === '') {
            return collect();
        }

        $itemSets = ItemSet::query()
            ->leftJoin('items', 'items.id', '=', 'item_sets.item_id')
            ->where('item_sets.company_id', $company->id)
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

        return $itemSets->map(function ($set) use ($company) {
            $hasApprovalItemsetId = Schema::hasColumn('approval_items', 'itemset_id');
            $hasSaleReturnItemsetId = Schema::hasColumn('sale_return_items', 'itemset_id');

            $approvalHistory = DB::table('approval_items as ai')
                ->join('approval_headers as ah', 'ah.id', '=', 'ai.approval_id')
                ->where('ah.company_id', $company->id)
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
                ->get(['ah.approval_no', 'ah.approval_date', 'ai.status'])
                ->map(function ($r) {
                    $date = $r->approval_date ? Carbon::parse($r->approval_date)->format('d-m-Y') : '-';
                    $status = $r->status ?: '-';
                    return "{$r->approval_no} ({$date}) [{$status}]";
                })
                ->values()
                ->all();

            $saleHistory = DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->where('s.company_id', $company->id)
                ->where('si.itemset_id', $set->id)
                ->orderBy('s.sale_date')
                ->orderBy('s.id')
                ->get(['s.voucher_no', 's.sale_date'])
                ->map(function ($r) {
                    $date = $r->sale_date ? Carbon::parse($r->sale_date)->format('d-m-Y') : '-';
                    return "{$r->voucher_no} ({$date})";
                })
                ->values()
                ->all();

            $returnHistory = DB::table('sale_return_items as sri')
                ->join('sale_returns as sr', 'sr.id', '=', 'sri.sale_return_id')
                ->leftJoin('sale_items as si', 'si.id', '=', 'sri.sale_item_id')
                ->where('sr.company_id', $company->id)
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
                ->get(['sr.return_voucher_no', 'sr.return_date'])
                ->map(function ($r) {
                    $date = $r->return_date ? Carbon::parse($r->return_date)->format('d-m-Y') : '-';
                    return "{$r->return_voucher_no} ({$date})";
                })
                ->values()
                ->all();

            $currentStatus = 'In Stock';
            if (!empty($returnHistory)) {
                $currentStatus = 'Returned';
            } elseif (!empty($saleHistory) || (int) ($set->is_sold ?? 0) === 1) {
                $currentStatus = 'Sold';
            } elseif (!empty($approvalHistory)) {
                $currentStatus = 'Approval';
            }

            return [
                'id' => $set->id,
                'item_name' => $set->item_name,
                'label_code' => $set->qr_code ?: $set->barcode,
                'label_created_at_fmt' => $set->created_at ? Carbon::parse($set->created_at)->format('d-m-Y h:i A') : '-',
                'label_printed_at_fmt' => $set->printed_at ? Carbon::parse($set->printed_at)->format('d-m-Y h:i A') : '-',
                'approval_history' => $approvalHistory,
                'sale_history' => $saleHistory,
                'return_history' => $returnHistory,
                'current_status' => $currentStatus,
            ];
        })->values();
    }
}
