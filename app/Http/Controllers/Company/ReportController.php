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
use Illuminate\Support\Facades\Crypt;
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
            $totals = $this->salesSummaryTotals($company, $request);

            return DataTables::of($this->salesSummaryBaseQuery($company, $request)->latest())
                ->addIndexColumn()
                ->addColumn('customer_name', fn($row) => optional($row->customer)->name ?? '-')
                ->addColumn('remarks', fn($row) => $row->remarks ?? '-')
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
                ->with(['totals' => $totals])
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
            fclose($out);
        }, 'sales_summary_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function salesSummaryPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->salesSummaryBaseQuery($company, $request)->latest()->get();
        $totals = $this->salesSummaryTotals($company, $request);

        return Pdf::loadView('company.reports.pdf.sales_summary', compact('company', 'rows', 'totals'))
            ->setPaper('a4', 'portrait')
            ->download('sales_summary_report.pdf');
    }

    public function purchaseReceiverSummary(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $totals = $this->purchaseReceiverSummaryTotals($company, $request);

            return DataTables::of($this->purchaseReceiverSummaryBaseQuery($company, $request)->orderByDesc('sr.id'))
                ->addIndexColumn()
                ->editColumn('return_date', fn($row) => $row->return_date ? Carbon::parse($row->return_date)->format('d-m-Y') : '-')
                ->editColumn('source_type', fn($row) => ucfirst((string) ($row->source_type ?: 'sale')))
                ->addColumn('remarks', fn($row) => $row->remarks ?? '-')
                ->addColumn('created_by', fn($row) => $row->created_by ?? '-')
                ->addColumn('qty_pcs', fn($row) => (int) ($row->total_qty ?? 0))
                ->addColumn('gross_weight', fn($row) => number_format((float) ($row->total_gross_weight ?? 0), 3))
                ->addColumn('net_weight', fn($row) => number_format((float) ($row->total_net_weight ?? 0), 3))
                ->addColumn('fine_weight', fn($row) => number_format((float) ($row->total_fine_weight ?? 0), 3))
                ->addColumn('metal_amount', fn($row) => number_format((float) ($row->total_metal_amount ?? 0), 2))
                ->addColumn('labour_amount', fn($row) => number_format((float) ($row->total_labour_amount ?? 0), 2))
                ->addColumn('other_amount', fn($row) => number_format((float) ($row->total_other_amount ?? 0), 2))
                ->editColumn('return_total', fn($row) => number_format((float) ($row->return_total ?? 0), 2))
                ->with(['totals' => $totals])
                ->make(true);
        }

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('company.reports.purchase_receiver_summary', compact('company', 'customers'));
    }

    public function purchaseReceiverSummaryExcel(Request $request, $slug): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->purchaseReceiverSummaryBaseQuery($company, $request)->orderByDesc('sr.id')->get();
        $totals = $this->purchaseReceiverSummaryTotals($company, $request);

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
                'TOTAL',
                '',
                '',
                '',
                (int) ($totals['qty_pcs'] ?? 0),
                number_format((float) ($totals['gross_weight'] ?? 0), 3, '.', ''),
                number_format((float) ($totals['net_weight'] ?? 0), 3, '.', ''),
                number_format((float) ($totals['fine_weight'] ?? 0), 3, '.', ''),
                number_format((float) ($totals['metal_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($totals['labour_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($totals['other_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($totals['return_total'] ?? 0), 2, '.', ''),
                '',
                '',
            ]);
            fclose($out);
        }, 'purchase_receiver_summary_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function purchaseReceiverSummaryPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->purchaseReceiverSummaryBaseQuery($company, $request)->orderByDesc('sr.id')->get();
        $totals = $this->purchaseReceiverSummaryTotals($company, $request);

        return Pdf::loadView('company.reports.pdf.purchase_receiver_summary', compact('company', 'rows', 'totals'))
            ->setPaper('a4', 'portrait')
            ->download('purchase_receiver_summary_report.pdf');
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
                ->addColumn('remarks', fn($row) => $row->remarks ?? '-')
                ->addColumn('created_by', fn($row) => optional($row->creator)->name ?? '-')
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
                ->addColumn('approval_history_html', fn($row) => $this->historyToLinks($row['approval_history'] ?? [], 'approval', $slug))
                ->addColumn('sale_history_html', fn($row) => $this->historyToLinks($row['sale_history'] ?? [], 'sale', $slug))
                ->addColumn('return_history_html', fn($row) => $this->historyToLinks($row['return_history'] ?? [], 'return', $slug))
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

    private function purchaseReceiverSummaryBaseQuery(Company $company, Request $request)
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
            ->where('sr.company_id', $company->id)
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

        $grossWeightExpr = $hasReturnItemsetId
            ? 'COALESCE(si.gross_weight, iset.gross_weight, 0)'
            : 'COALESCE(si.gross_weight, 0)';
        $netWeightExpr = $hasReturnItemsetId
            ? 'COALESCE(si.net_weight, iset.net_weight, 0)'
            : 'COALESCE(si.net_weight, 0)';

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

    private function salesSummaryTotals(Company $company, Request $request): array
    {
        $applySalesFilters = function ($query) use ($company, $request) {
            $query->where('sales.company_id', $company->id);

            if ($request->filled('customer_id')) {
                $query->where('sales.customer_id', (int) $request->customer_id);
            }
            if ($request->filled('from_date') && $request->filled('to_date')) {
                $query->whereBetween('sales.sale_date', [$request->from_date, $request->to_date]);
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

    private function purchaseReceiverSummaryTotals(Company $company, Request $request): array
    {
        $rows = $this->purchaseReceiverSummaryBaseQuery($company, $request)->get();

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
                ->get(['ah.id as approval_id', 'ah.approval_no', 'ah.approval_date', 'ai.status'])
                ->map(function ($r) {
                    $date = $r->approval_date ? Carbon::parse($r->approval_date)->format('d-m-Y') : '-';
                    $status = $r->status ?: '-';
                    return [
                        'id' => (int) $r->approval_id,
                        'label' => "{$r->approval_no} ({$date}) [{$status}]",
                    ];
                })
                ->values()
                ->all();

            $saleHistory = DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->where('s.company_id', $company->id)
                ->where('si.itemset_id', $set->id)
                ->orderBy('s.sale_date')
                ->orderBy('s.id')
                ->get(['s.id as sale_id', 's.voucher_no', 's.sale_date'])
                ->map(function ($r) {
                    $date = $r->sale_date ? Carbon::parse($r->sale_date)->format('d-m-Y') : '-';
                    return [
                        'id' => (int) $r->sale_id,
                        'label' => "{$r->voucher_no} ({$date})",
                    ];
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
                ->get(['sr.id as return_id', 'sr.return_voucher_no', 'sr.return_date'])
                ->map(function ($r) {
                    $date = $r->return_date ? Carbon::parse($r->return_date)->format('d-m-Y') : '-';
                    return [
                        'id' => (int) $r->return_id,
                        'label' => "{$r->return_voucher_no} ({$date})",
                    ];
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

    private function historyToLinks(array $history, string $type, string $slug): string
    {
        if (empty($history)) {
            return '<span class="text-muted">-</span>';
        }

        $lines = collect($history)->map(function ($row) use ($type, $slug) {
            $id = (int) ($row['id'] ?? 0);
            $label = (string) ($row['label'] ?? '-');
            $escapedLabel = e($label);

            if ($id <= 0) {
                return $escapedLabel;
            }

            $encryptedId = Crypt::encryptString((string) $id);
            $url = match ($type) {
                'approval' => route('company.approval.view', [$slug, $encryptedId]),
                'sale' => route('company.sales.show', [$slug, $encryptedId]),
                'return' => route('company.returns.show', [$slug, $encryptedId]),
                default => '',
            };

            if ($url === '') {
                return $escapedLabel;
            }

            return '<a href="' . e($url) . '">' . $escapedLabel . '</a>';
        })->all();

        return implode('<br>', $lines);
    }

    private function historyToText(array $history): string
    {
        if (empty($history)) {
            return '-';
        }

        $lines = collect($history)
            ->map(fn($row) => (string) ($row['label'] ?? '-'))
            ->filter(fn($label) => $label !== '')
            ->values()
            ->all();

        return empty($lines) ? '-' : implode(' | ', $lines);
    }
}
