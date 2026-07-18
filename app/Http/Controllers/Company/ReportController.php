<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\ApprovalHeader;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ItemSet;
use App\Models\Sale;
use App\Models\VisitingCard;
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
    public function visitingCardsCreate(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        return view('company.reports.visiting_cards_create', compact('company'));
    }

    public function visitingCards(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $fromDate = $request->input('from_date', now()->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));
        $selectedDate = $request->input('selected_date');

        if ($request->ajax()) {
            $query = $this->visitingCardsBaseQuery($company->id, $fromDate, $toDate, $selectedDate);
            $search = trim((string) data_get($request->input('search'), 'value', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_no', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('pincode', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }
            $query->latest('id');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('sr_no', fn($row) => null)
                ->addColumn('created_at_fmt', fn($row) => optional($row->created_at)->format('d-m-Y h:i A') ?? '-')
                ->addColumn('name_fmt', fn($row) => $row->name ?: '-')
                ->addColumn('mobile_fmt', fn($row) => $row->mobile_no ?: '-')
                ->addColumn('city_fmt', fn($row) => $row->city ?: '-')
                ->addColumn('pincode_fmt', fn($row) => $row->pincode ?: '-')
                ->addColumn('address_fmt', fn($row) => $row->address ?: '-')
                ->addColumn('email_fmt', fn($row) => $row->email ?: '-')
                ->addColumn('action', function ($row) use ($company) {
                    $showUrl = route('company.reports.visiting-cards.show', [$company->slug, $row->id]);
                    $updateUrl = route('company.reports.visiting-cards.update', [$company->slug, $row->id]);
                    $deleteUrl = route('company.reports.visiting-cards.destroy', [$company->slug, $row->id]);

                    return '
                        <button type="button" class="btn btn-sm btn-primary edit-visiting-card"
                            data-show-url="' . e($showUrl) . '"
                            data-update-url="' . e($updateUrl) . '">
                            Edit
                        </button>
                        <button type="button" class="btn btn-sm btn-danger delete-visiting-card"
                            data-delete-url="' . e($deleteUrl) . '">
                            Delete
                        </button>
                    ';
                })
                ->with([
                    'selected_date' => $selectedDate,
                ])
                ->rawColumns(['action'])
                ->make(true);
        }

        $summaryRows = VisitingCard::query()
            ->where('company_id', $company->id)
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->orderBy('created_at')
            ->get();

        $summary = $summaryRows->groupBy(fn($row) => $row->created_at->format('Y-m-d'))
            ->map(fn($group, $date) => [
                'date' => $date,
                'total_uploads' => $group->count(),
            ])
            ->values();

        return view('company.reports.visiting_cards', compact('company', 'fromDate', 'toDate', 'summary'));
    }

    public function visitingCardsShow(Request $request, $slug, int $id)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $card = VisitingCard::where('company_id', $company->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $card,
        ]);
    }

    public function visitingCardsUpdate(Request $request, $slug, int $id)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $card = VisitingCard::where('company_id', $company->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'mobile_no' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:191',
            'city' => 'nullable|string|max:191',
            'pincode' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $card->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Visiting card updated successfully.',
            'data' => $card->fresh(),
        ]);
    }

    public function visitingCardsDestroy(Request $request, $slug, int $id)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $card = VisitingCard::where('company_id', $company->id)->findOrFail($id);
        $card->delete();

        return response()->json([
            'success' => true,
            'message' => 'Visiting card deleted successfully.',
        ]);
    }

    public function visitingCardsExcel(Request $request, $slug): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->visitingCardsBaseQuery(
            (int) $company->id,
            $request->input('from_date', now()->format('Y-m-d')),
            $request->input('to_date', now()->format('Y-m-d')),
            $request->input('selected_date')
        )->latest('id')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Mobile', 'Email', 'City', 'Pincode', 'Address', 'Date']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->name ?: '-',
                    $r->mobile_no ?: '-',
                    $r->email ?: '-',
                    $r->city ?: '-',
                    $r->pincode ?: '-',
                    $r->address ?: '-',
                    optional($r->created_at)->format('d-m-Y h:i A') ? "'" . optional($r->created_at)->format('d-m-Y h:i A') : '-',
                ]);
            }
            fclose($out);
        }, 'visiting_cards_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function visitingCardsPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->visitingCardsBaseQuery(
            (int) $company->id,
            $request->input('from_date', now()->format('Y-m-d')),
            $request->input('to_date', now()->format('Y-m-d')),
            $request->input('selected_date')
        )->latest('id')->get();

        $fromDate = $request->input('from_date', now()->format('Y-m-d'));
        $toDate = $request->input('to_date', now()->format('Y-m-d'));

        return Pdf::loadView('company.reports.pdf.visiting_cards', compact('company', 'rows', 'fromDate', 'toDate'))
            ->setPaper('a4', 'landscape')
            ->download('visiting_cards_report.pdf');
    }

    private function visitingCardsBaseQuery(int $companyId, string $fromDate, string $toDate, ?string $selectedDate = null)
    {
        return VisitingCard::query()
            ->where('company_id', $companyId)
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->when($selectedDate, function ($q) use ($selectedDate) {
                $q->whereDate('created_at', $selectedDate);
            });
    }

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

    public function workerLoss(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $query = $this->workerLossBaseQuery($company, $request);
            $totals = $this->workerLossTotals($company, $request);
            $summary = $this->workerLossSummary($company, $request);

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('process_datetime', function ($row) {
                    return $row->process_datetime
                        ? Carbon::parse($row->process_datetime)->format('d-m-Y h:i A')
                        : '-';
                })
                ->editColumn('worker_name', fn($row) => $row->worker_name ?: '-')
                ->addColumn('voucher_no_html', fn($row) => $this->workerLossVoucherLink($company, $row, $row->voucher_no ?: '-'))
                ->addColumn('buch_no_html', fn($row) => $this->workerLossVoucherLink($company, $row, $row->buch_no ?: '-'))
                ->editColumn('source_wt', fn($row) => number_format((float) ($row->source_wt ?? 0), 3, '.', ''))
                ->editColumn('receive_wt', fn($row) => number_format((float) ($row->receive_wt ?? 0), 3, '.', ''))
                ->editColumn('bhuko', fn($row) => number_format((float) ($row->bhuko ?? 0), 3, '.', ''))
                ->editColumn('loss', fn($row) => number_format((float) ($row->loss ?? 0), 3, '.', ''))
                ->with([
                    'totals' => $totals,
                    'worker_summary' => $summary['workers'],
                    'stage_summary' => $summary['stages'],
                ])
                ->rawColumns(['voucher_no_html', 'buch_no_html'])
                ->make(true);
        }

        $workers = DB::table('job_workers')
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $defaultFromDate = now()->subDays(6)->toDateString();
        $defaultToDate = now()->toDateString();

        return view('company.reports.worker_loss', compact('company', 'workers', 'defaultFromDate', 'defaultToDate'));
    }

    public function workerLossSuggest(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $term = trim((string) $request->get('q', ''));
        $limit = max(1, min((int) $request->get('limit', 12), 50));

        $filterRequest = new Request($request->except(['q', 'limit', 'voucher_no']));
        $baseQuery = $this->workerLossBaseQuery($company, $filterRequest);
        $query = DB::query()
            ->fromSub($baseQuery, 'loss_rows')
            ->when($term !== '', fn($q) => $q->where('voucher_no', 'like', "%{$term}%"))
            ->selectRaw('voucher_id, voucher_no, MAX(process_datetime) as latest_process_datetime')
            ->groupBy('voucher_id', 'voucher_no')
            ->orderByDesc('latest_process_datetime')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $query->map(function ($row) {
                return [
                    'id' => (int) $row->voucher_id,
                    'voucher_no' => $row->voucher_no,
                    'date_time' => $row->latest_process_datetime
                        ? Carbon::parse($row->latest_process_datetime)->format('d-m-Y h:i A')
                        : null,
                ];
            })->values(),
        ]);
    }

    public function workerLossPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->workerLossBaseQuery($company, $request)->get();
        $totals = $this->workerLossTotals($company, $request);
        $summary = $this->workerLossSummary($company, $request);

        return Pdf::loadView('company.reports.pdf.worker_loss', compact('company', 'rows', 'totals', 'summary', 'request'))
            ->setPaper('a4', 'landscape')
            ->download('worker_loss_report.pdf');
    }

    public function workerLossExcel(Request $request, $slug): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->workerLossBaseQuery($company, $request)->get();
        $totals = $this->workerLossTotals($company, $request);
        $summary = $this->workerLossSummary($company, $request);

        return response()->streamDownload(function () use ($rows, $totals, $summary) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date Time', 'Worker', 'Voucher No', 'B. No', 'Stage', 'Source Wt', 'Receive Wt', 'Bhuko', 'Loss']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->process_datetime ? Carbon::parse($row->process_datetime)->format('d-m-Y h:i A') : '-',
                    $row->worker_name ?: '-',
                    $row->voucher_no ?: '-',
                    $row->buch_no ?: '-',
                    $row->stage ?: '-',
                    number_format((float) ($row->source_wt ?? 0), 3, '.', ''),
                    number_format((float) ($row->receive_wt ?? 0), 3, '.', ''),
                    number_format((float) ($row->bhuko ?? 0), 3, '.', ''),
                    number_format((float) ($row->loss ?? 0), 3, '.', ''),
                ]);
            }
            fputcsv($out, [
                'TOTAL',
                '',
                '',
                '',
                '',
                number_format((float) ($totals['source_wt'] ?? 0), 3, '.', ''),
                number_format((float) ($totals['receive_wt'] ?? 0), 3, '.', ''),
                number_format((float) ($totals['bhuko'] ?? 0), 3, '.', ''),
                number_format((float) ($totals['loss'] ?? 0), 3, '.', ''),
            ]);
            fputcsv($out, []);
            fputcsv($out, ['Worker Summary']);
            fputcsv($out, ['Worker', 'Rows', 'Source Wt', 'Receive Wt', 'Bhuko', 'Loss']);
            foreach ($summary['workers'] as $row) {
                fputcsv($out, [$row['label'], $row['rows'], $row['source_wt'], $row['receive_wt'], $row['bhuko'], $row['loss']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Stage Summary']);
            fputcsv($out, ['Stage', 'Rows', 'Source Wt', 'Receive Wt', 'Bhuko', 'Loss']);
            foreach ($summary['stages'] as $row) {
                fputcsv($out, [$row['label'], $row['rows'], $row['source_wt'], $row['receive_wt'], $row['bhuko'], $row['loss']]);
            }
            fclose($out);
        }, 'worker_loss_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function approvalOutstanding(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $baseQuery = $this->approvalOutstandingBaseQuery($company, $request);
            $summary = $this->approvalOutstandingTotals((clone $baseQuery)->get());

            return DataTables::of($baseQuery->latest('approval_date'))
                ->addIndexColumn()
                ->addColumn('customer_name', fn($row) => optional($row->customer)->name ?? '-')
                ->addColumn('approval_date_fmt', fn($row) => optional($row->approval_date)?->format('d-m-Y') ?? '-')
                ->addColumn('remarks', fn($row) => $row->remarks ?? '-')
                ->addColumn('created_by', fn($row) => optional($row->creator)->name ?? '-')
                ->addColumn('pending_items', fn($row) => (int) ($row->pending_items_count ?? 0))
                ->addColumn('pending_net_weight_fmt', fn($row) => number_format((float) ($row->pending_net_weight ?? 0), 3))
                ->addColumn('pending_total_amount_fmt', fn($row) => number_format((float) ($row->pending_total_amount ?? 0), 2))
                ->with(['summary' => $summary])
                ->make(true);
        }

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('company.reports.approval_outstanding', compact('company', 'customers'));
    }

    public function approvalOutstandingDetails($slug, ApprovalHeader $approval)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        abort_unless((int) $approval->company_id === (int) $company->id, 404);

        $approval->load(['customer', 'creator']);

        $items = $approval->items()
            ->with(['itemSet.item', 'legacyItemSet.item', 'item'])
            ->where('status', 'pending')
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                $itemSet = $row->itemSet ?? $row->legacyItemSet;
                $item = optional($itemSet)->item ?? $row->item;

                return [
                    'qr_code' => $row->qr_code ?? optional($itemSet)->qr_code ?? '-',
                    'huid' => $row->huid ?? optional($itemSet)->HUID ?? '-',
                    'item_name' => optional($item)->item_name ?? '-',
                    'gross_weight' => number_format((float) ($row->gross_weight ?? optional($itemSet)->gross_weight ?? 0), 3),
                    'other_weight' => number_format((float) ($row->other_weight ?? optional($itemSet)->other ?? 0), 3),
                    'net_weight' => number_format((float) ($row->net_weight ?? optional($itemSet)->net_weight ?? 0), 3),
                    'total_amount' => number_format((float) ($row->total_amount ?? 0), 2),
                    'status' => $row->status ?? '-',
                ];
            });

        return response()->json([
            'success' => true,
            'approval' => [
                'approval_no' => $approval->approval_no,
                'approval_date' => optional($approval->approval_date)?->format('d-m-Y') ?? '-',
                'customer_name' => optional($approval->customer)->name ?? '-',
                'status' => $approval->status ?? '-',
                'remarks' => $approval->remarks ?? '-',
                'created_by' => optional($approval->creator)->name ?? '-',
            ],
            'summary' => [
                'pending_pcs' => $items->count(),
                'pending_net_weight' => number_format((float) $items->sum(fn($row) => (float) str_replace(',', '', $row['net_weight'])), 3),
                'pending_amount' => number_format((float) $items->sum(fn($row) => (float) str_replace(',', '', $row['total_amount'])), 2),
            ],
            'items' => $items->values(),
        ]);
    }

    public function approvalOutstandingExcel(Request $request, $slug): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->approvalOutstandingBaseQuery($company, $request)->latest('approval_date')->get();
        $summary = $this->approvalOutstandingTotals($rows);

        return response()->streamDownload(function () use ($rows, $summary) {
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
            fputcsv($out, [
                'TOTAL',
                '',
                '',
                '',
                (int) ($summary['pending_pcs'] ?? 0),
                number_format((float) ($summary['pending_net_weight'] ?? 0), 3, '.', ''),
                '',
                '',
                '',
            ]);
            fclose($out);
        }, 'approval_outstanding_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function approvalOutstandingPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->approvalOutstandingBaseQuery($company, $request)->latest('approval_date')->get();
        $summary = $this->approvalOutstandingTotals($rows);

        return Pdf::loadView('company.reports.pdf.approval_outstanding', compact('company', 'rows', 'summary'))
            ->setPaper('a4', 'portrait')
            ->download('approval_outstanding_report.pdf');
    }

    public function outstandingAmount(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $baseQuery = $this->outstandingAmountBaseQuery($company, $request);
            $summary = $this->outstandingAmountTotals((clone $baseQuery)->get());

            return DataTables::of($baseQuery->latest('id'))
                ->addIndexColumn()
                ->addColumn('sale_date_fmt', fn($row) => optional($row->sale_date)?->format('d-m-Y') ?? '-')
                ->addColumn('customer_name', fn($row) => optional($row->customer)->name ?? '-')
                ->addColumn('city', fn($row) => optional($row->customer)->city ?? '-')
                ->addColumn('gross_weight_fmt', fn($row) => number_format((float) ($row->sum_gross_weight ?? 0), 3))
                ->addColumn('net_weight_fmt', fn($row) => number_format((float) ($row->sum_net_weight ?? 0), 3))
                ->addColumn('amount_in_fmt', fn($row) => number_format((float) ($row->received_amount ?? 0), 2))
                ->addColumn('amount_out_fmt', fn($row) => number_format((float) ($row->paid_amount ?? 0), 2))
                ->addColumn('pending_amount_fmt', function ($row) {
                    $received = (float) ($row->received_amount ?? 0);
                    $out = (float) ($row->paid_amount ?? 0);
                    $pending = max(0, (float) ($row->net_total ?? 0) - ($received - $out));
                    return number_format($pending, 2);
                })
                ->with(['summary' => $summary])
                ->make(true);
        }

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'city']);

        $cities = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->values();

        return view('company.reports.outstanding_amount', compact('company', 'customers', 'cities'));
    }

    public function outstandingAmountExcel(Request $request, $slug): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->outstandingAmountBaseQuery($company, $request)->latest('id')->get();
        $summary = $this->outstandingAmountTotals($rows);

        return response()->streamDownload(function () use ($rows, $summary) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Voucher No', 'Date', 'Party', 'City', 'Payment Mode', 'Gross Wt', 'Net Wt', 'Total Amount', 'Amount In', 'Amount Out', 'Pending']);
            foreach ($rows as $r) {
                $received = (float) ($r->received_amount ?? 0);
                $outAmt = (float) ($r->paid_amount ?? 0);
                $pending = max(0, (float) ($r->net_total ?? 0) - ($received - $outAmt));
                fputcsv($out, [
                    $r->voucher_no,
                    optional($r->sale_date)?->format('d-m-Y') ?? '-',
                    optional($r->customer)->name ?? '-',
                    optional($r->customer)->city ?? '-',
                    $r->payment_mode ?? '-',
                    number_format((float) ($r->sum_gross_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->sum_net_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->net_total ?? 0), 2, '.', ''),
                    number_format($received, 2, '.', ''),
                    number_format($outAmt, 2, '.', ''),
                    number_format($pending, 2, '.', ''),
                ]);
            }

            fputcsv($out, [
                'TOTAL',
                '',
                '',
                '',
                '',
                number_format((float) ($summary['gross_weight'] ?? 0), 3, '.', ''),
                number_format((float) ($summary['net_weight'] ?? 0), 3, '.', ''),
                number_format((float) ($summary['total_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($summary['amount_in'] ?? 0), 2, '.', ''),
                number_format((float) ($summary['amount_out'] ?? 0), 2, '.', ''),
                number_format((float) ($summary['pending_amount'] ?? 0), 2, '.', ''),
            ]);
            fclose($out);
        }, 'outstanding_amount_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function outstandingAmountPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->outstandingAmountBaseQuery($company, $request)->latest('id')->get();
        $summary = $this->outstandingAmountTotals($rows);
        $visible = [
            'default' => $this->isOutstandingToggleEnabled($request, ['use_default_report', 'use_default']),
            'date' => $this->isOutstandingToggleEnabled($request, ['use_date']),
            'party' => $this->isOutstandingToggleEnabled($request, ['use_customer', 'use_party']),
            'city' => $this->isOutstandingToggleEnabled($request, ['use_city']),
            'mode' => $this->isOutstandingToggleEnabled($request, ['use_payment_mode', 'use_mode']),
            'weight' => $this->isOutstandingToggleEnabled($request, ['use_weight']),
            'amount' => $this->isOutstandingToggleEnabled($request, ['use_amount']),
        ];

        if (!$visible['default'] && !$visible['date'] && !$visible['party'] && !$visible['city'] && !$visible['mode'] && !$visible['weight'] && !$visible['amount']) {
            $visible['default'] = true;
        }

        return Pdf::loadView('company.reports.pdf.outstanding_amount', compact('company', 'rows', 'summary', 'visible'))
            ->setPaper('a4', 'portrait')
            ->download('outstanding_amount_report.pdf');
    }

    public function outstandingAmountLedgerPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->outstandingAmountBaseQuery($company, $request)->latest('sale_date')->latest('id')->get();
        $summary = $this->outstandingAmountTotals($rows);

        return Pdf::loadView('company.reports.pdf.outstanding_amount_ledger', compact('company', 'rows', 'summary'))
            ->setPaper('a4', 'landscape')
            ->download('outstanding_amount_ledger_report.pdf');
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
        $hasReturnApprovalItemId = Schema::hasColumn('sale_return_items', 'approval_item_id');
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
            $query->leftJoin('item_sets as iset', 'iset.id', '=', 'sri.itemset_id')
                ->leftJoin('approval_items as ai_by_itemset', function ($join) {
                    $join->on('ai_by_itemset.itemset_id', '=', 'sri.itemset_id')
                        ->on('ai_by_itemset.approval_id', '=', 'sr.source_id');
                });
        }

        if ($hasReturnApprovalItemId) {
            $query->leftJoin('approval_items as ai', 'ai.id', '=', 'sri.approval_item_id');
        }

        $itemSetGrossExpr = $hasReturnItemsetId ? 'iset.gross_weight' : 'NULL';
        $itemSetNetExpr = $hasReturnItemsetId ? 'iset.net_weight' : 'NULL';
        $itemSetOtherExpr = $hasReturnItemsetId ? 'iset.sale_other' : 'NULL';

        $approvalGrossExpr = 'NULL';
        $approvalNetExpr = 'NULL';
        $approvalFineExpr = 'NULL';
        $approvalMetalExpr = 'NULL';
        $approvalLabourExpr = 'NULL';
        $approvalOtherExpr = 'NULL';
        $approvalTotalExpr = 'NULL';

        if ($hasReturnApprovalItemId && $hasReturnItemsetId) {
            $approvalGrossExpr = 'COALESCE(ai.gross_weight, ai_by_itemset.gross_weight)';
            $approvalNetExpr = 'COALESCE(ai.net_weight, ai_by_itemset.net_weight)';
            $approvalFineExpr = 'COALESCE(ai.total_fine_weight, ai_by_itemset.total_fine_weight)';
            $approvalMetalExpr = 'COALESCE(ai.metal_amount, ai_by_itemset.metal_amount)';
            $approvalLabourExpr = 'COALESCE(ai.labour_amount, ai_by_itemset.labour_amount)';
            $approvalOtherExpr = 'COALESCE(ai.other_amount, ai_by_itemset.other_amount)';
            $approvalTotalExpr = 'COALESCE(ai.total_amount, ai_by_itemset.total_amount)';
        } elseif ($hasReturnApprovalItemId) {
            $approvalGrossExpr = 'ai.gross_weight';
            $approvalNetExpr = 'ai.net_weight';
            $approvalFineExpr = 'ai.total_fine_weight';
            $approvalMetalExpr = 'ai.metal_amount';
            $approvalLabourExpr = 'ai.labour_amount';
            $approvalOtherExpr = 'ai.other_amount';
            $approvalTotalExpr = 'ai.total_amount';
        } elseif ($hasReturnItemsetId) {
            $approvalGrossExpr = 'ai_by_itemset.gross_weight';
            $approvalNetExpr = 'ai_by_itemset.net_weight';
            $approvalFineExpr = 'ai_by_itemset.total_fine_weight';
            $approvalMetalExpr = 'ai_by_itemset.metal_amount';
            $approvalLabourExpr = 'ai_by_itemset.labour_amount';
            $approvalOtherExpr = 'ai_by_itemset.other_amount';
            $approvalTotalExpr = 'ai_by_itemset.total_amount';
        }

        $grossWeightExpr = "COALESCE(si.gross_weight, {$approvalGrossExpr}, {$itemSetGrossExpr}, 0)";
        $netWeightExpr = "COALESCE(si.net_weight, {$approvalNetExpr}, {$itemSetNetExpr}, 0)";
        $fineWeightExpr = "COALESCE(si.fine_weight, {$approvalFineExpr}, 0)";
        $metalAmountExpr = "COALESCE(si.metal_amount, {$approvalMetalExpr}, 0)";
        $labourAmountExpr = "COALESCE(si.labour_amount, {$approvalLabourExpr}, 0)";
        $otherAmountExpr = "COALESCE(si.other_amount, NULLIF({$approvalOtherExpr}, 0), {$itemSetOtherExpr}, 0)";
        $totalAmountExpr = "COALESCE(NULLIF(si.total_amount, 0), NULLIF({$approvalTotalExpr}, 0), ({$metalAmountExpr} + {$labourAmountExpr} + {$otherAmountExpr}), 0)";

        $query->selectRaw("
            sr.id,
            sr.return_voucher_no,
            sr.return_date,
            COALESCE(NULLIF(sr.return_total, 0), SUM({$totalAmountExpr}), 0) as return_total,
            sr.remarks,
            sr.source_type,
            sr.created_at,
            COALESCE(sc.name, ac.name, '-') as customer_name,
            COALESCE(su.name, au.name, '-') as created_by,
            COUNT(sri.id) as total_qty,
            COALESCE(SUM({$grossWeightExpr}), 0) as total_gross_weight,
            COALESCE(SUM({$netWeightExpr}), 0) as total_net_weight,
            COALESCE(SUM({$fineWeightExpr}), 0) as total_fine_weight,
            COALESCE(SUM({$metalAmountExpr}), 0) as total_metal_amount,
            COALESCE(SUM({$labourAmountExpr}), 0) as total_labour_amount,
            COALESCE(SUM({$otherAmountExpr}), 0) as total_other_amount
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

    private function workerLossBaseQuery(Company $company, Request $request)
    {
        $castingReceive = DB::table('casting_release_items as cr')
            ->join('vacuum_vouchers as vv', 'vv.id', '=', 'cr.vacuum_voucher_id')
            ->leftJoin('vacuum_voucher_items as vvi', 'vvi.id', '=', 'cr.vacuum_voucher_item_id')
            ->leftJoin('casting_metal_issue_items as cmi', function ($join) {
                $join->on('cmi.company_id', '=', 'cr.company_id')
                    ->on('cmi.vacuum_voucher_item_id', '=', 'cr.vacuum_voucher_item_id');
            })
            ->leftJoin('job_workers as jw', 'jw.id', '=', 'vv.job_worker_id')
            ->where('cr.company_id', $company->id)
            ->where(function ($query) {
                $query->whereNotNull('cr.release_tree_wt')
                    ->orWhereNotNull('cr.release_tree_bhuko')
                    ->orWhereNotNull('cr.loss');
            })
            ->selectRaw("
                'Casting Receive' as stage,
                vv.job_worker_id as worker_id,
                jw.name as worker_name,
                vv.id as voucher_id,
                vv.voucher_no as voucher_no,
                vvi.buch_no as buch_no,
                cmi.issue_silver_wt as source_wt,
                cr.release_tree_wt as receive_wt,
                cr.release_tree_bhuko as bhuko,
                cr.loss as loss,
                COALESCE(cr.released_at, cr.created_at) as process_datetime
            ");

        $treeCuttingReceive = DB::table('tree_cutting_receive_items as tcr')
            ->join('vacuum_vouchers as vv', 'vv.id', '=', 'tcr.vacuum_voucher_id')
            ->leftJoin('tree_cutting_issue_items as tci', 'tci.id', '=', 'tcr.tree_cutting_issue_item_id')
            ->leftJoin('vacuum_voucher_items as vvi', function ($join) {
                $join->on('vvi.id', '=', DB::raw('COALESCE(tcr.vacuum_voucher_item_id, tci.vacuum_voucher_item_id)'));
            })
            ->leftJoin('job_workers as jw', function ($join) {
                $join->on('jw.id', '=', DB::raw('COALESCE(tcr.job_worker_id, tci.job_worker_id)'));
            })
            ->where('tcr.company_id', $company->id)
            ->where(function ($query) {
                $query->whereNotNull('tcr.receive_pc_wt')
                    ->orWhereNotNull('tcr.receive_tree_bhuko')
                    ->orWhereNotNull('tcr.loss');
            })
            ->selectRaw("
                'Tree Cutting Receive' as stage,
                COALESCE(tcr.job_worker_id, tci.job_worker_id) as worker_id,
                jw.name as worker_name,
                vv.id as voucher_id,
                vv.voucher_no as voucher_no,
                CASE
                    WHEN COALESCE(tcr.is_custom, tci.is_custom, 0) = 1
                        THEN COALESCE(tcr.custom_buch_no, tci.custom_buch_no)
                    ELSE COALESCE(vvi.buch_no, tcr.custom_buch_no, tci.custom_buch_no)
                END as buch_no,
                tci.receive_tree_wt as source_wt,
                tcr.receive_pc_wt as receive_wt,
                tcr.receive_tree_bhuko as bhuko,
                tcr.loss as loss,
                COALESCE(tcr.received_at, tcr.created_at) as process_datetime
            ");

        $query = DB::query()
            ->fromSub($castingReceive->unionAll($treeCuttingReceive), 'worker_loss')
            ->select('worker_loss.*');

        if ($request->filled('from_date')) {
            $query->whereDate('process_datetime', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('process_datetime', '<=', $request->input('to_date'));
        }
        if ($request->filled('worker_id')) {
            $query->where('worker_id', (int) $request->input('worker_id'));
        }
        if ($request->filled('stage')) {
            $query->where('stage', $request->input('stage'));
        }
        if ($request->filled('voucher_no')) {
            $voucherNo = trim((string) $request->input('voucher_no'));
            $query->where('voucher_no', 'like', "%{$voucherNo}%");
        }

        match ($request->input('loss_type')) {
            'plus' => $query->where('loss', '>', 0),
            'minus' => $query->where('loss', '<', 0),
            'zero' => $query->where(function ($q) {
                $q->where('loss', 0)->orWhereNull('loss');
            }),
            default => null,
        };

        if ($this->isTruthyRequestValue($request->input('only_loss'))) {
            $query->where('loss', '!=', 0);
        }

        return $query->orderByDesc('process_datetime');
    }

    private function workerLossTotals(Company $company, Request $request): array
    {
        $rows = $this->workerLossBaseQuery($company, $request)->get();

        return [
            'row_count' => $rows->count(),
            'source_wt' => (float) $rows->sum(fn($row) => (float) ($row->source_wt ?? 0)),
            'receive_wt' => (float) $rows->sum(fn($row) => (float) ($row->receive_wt ?? 0)),
            'bhuko' => (float) $rows->sum(fn($row) => (float) ($row->bhuko ?? 0)),
            'loss' => (float) $rows->sum(fn($row) => (float) ($row->loss ?? 0)),
        ];
    }

    private function workerLossSummary(Company $company, Request $request): array
    {
        $rows = $this->workerLossBaseQuery($company, $request)->get();
        $format = fn($value) => number_format((float) $value, 3, '.', '');

        $mapSummary = function ($groupedRows, string $labelField) use ($format) {
            return $groupedRows->map(function ($group, $key) use ($format, $labelField) {
                $first = $group->first();

                return [
                    'label' => $labelField === 'worker_name'
                        ? ($first->worker_name ?: 'No Worker')
                        : ($key ?: '-'),
                    'rows' => $group->count(),
                    'source_wt' => $format($group->sum(fn($row) => (float) ($row->source_wt ?? 0))),
                    'receive_wt' => $format($group->sum(fn($row) => (float) ($row->receive_wt ?? 0))),
                    'bhuko' => $format($group->sum(fn($row) => (float) ($row->bhuko ?? 0))),
                    'loss' => $format($group->sum(fn($row) => (float) ($row->loss ?? 0))),
                ];
            })->values();
        };

        return [
            'workers' => $mapSummary($rows->groupBy(fn($row) => $row->worker_id ?: 'no-worker'), 'worker_name'),
            'stages' => $mapSummary($rows->groupBy(fn($row) => $row->stage ?: '-'), 'stage'),
        ];
    }

    private function workerLossVoucherLink(Company $company, object $row, string $label): string
    {
        if (empty($row->voucher_id)) {
            return e($label);
        }

        $url = route('company.vacuum-vouchers.show', [
            $company->slug,
            Crypt::encryptString((string) $row->voucher_id),
        ]);

        return '<a href="' . e($url) . '" target="_blank" class="worker-loss-link">' . e($label) . '</a>';
    }

    private function isTruthyRequestValue($value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'on', 'yes'], true);
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

    private function approvalOutstandingTotals(Collection $rows): array
    {
        return [
            'voucher_count' => (int) $rows->count(),
            'pending_pcs' => (int) $rows->sum(fn($r) => (int) ($r->pending_items_count ?? 0)),
            'pending_net_weight' => (float) $rows->sum(fn($r) => (float) ($r->pending_net_weight ?? 0)),
            'pending_amount' => (float) $rows->sum(fn($r) => (float) ($r->pending_total_amount ?? 0)),
        ];
    }

    private function outstandingAmountBaseQuery(Company $company, Request $request)
    {
        $query = Sale::with(['customer'])
            ->withSum('saleItems as sum_gross_weight', 'gross_weight')
            ->withSum('saleItems as sum_net_weight', 'net_weight')
            ->where('company_id', $company->id);

        $hasUseToggles = $this->hasOutstandingUseToggles($request);
        $useDate = $this->isOutstandingToggleEnabled($request, ['use_date']);
        $useCustomer = $this->isOutstandingToggleEnabled($request, ['use_customer', 'use_party']);
        $useCity = $this->isOutstandingToggleEnabled($request, ['use_city']);
        $useMode = $this->isOutstandingToggleEnabled($request, ['use_payment_mode', 'use_mode']);
        $useWeight = $this->isOutstandingToggleEnabled($request, ['use_weight']);
        $useAmount = $this->isOutstandingToggleEnabled($request, ['use_amount']);

        if (($useCustomer || !$hasUseToggles) && $request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->customer_id);
        }
        if (($useCity || !$hasUseToggles) && $request->filled('city')) {
            $city = trim((string) $request->city);
            $query->whereHas('customer', fn($q) => $q->where('city', $city));
        }
        if (($useMode || !$hasUseToggles) && $request->filled('payment_mode')) {
            $query->where('payment_mode', trim((string) $request->payment_mode));
        }
        $hasDateInput = $request->filled('from_date') || $request->filled('to_date');
        if ($useDate || !$hasUseToggles || $hasDateInput) {
            if ($request->filled('from_date') && $request->filled('to_date')) {
                $query->whereBetween('sale_date', [$request->from_date, $request->to_date]);
            } elseif ($request->filled('from_date')) {
                $query->whereDate('sale_date', '>=', $request->from_date);
            } elseif ($request->filled('to_date')) {
                $query->whereDate('sale_date', '<=', $request->to_date);
            }
        }

        if ($useWeight || !$hasUseToggles) {
            if ($request->filled('weight_from')) {
                $minWeight = (float) $request->weight_from;
                $query->having('sum_net_weight', '>=', $minWeight);
            }
            if ($request->filled('weight_to')) {
                $maxWeight = (float) $request->weight_to;
                $query->having('sum_net_weight', '<=', $maxWeight);
            }
        }
        if ($useAmount || !$hasUseToggles) {
            if ($request->filled('amount_from')) {
                $query->where('net_total', '>=', (float) $request->amount_from);
            }
            if ($request->filled('amount_to')) {
                $query->where('net_total', '<=', (float) $request->amount_to);
            }
        }

        return $query;
    }

    private function isOutstandingToggleEnabled(Request $request, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!$request->has($key)) {
                continue;
            }
            $value = $request->input($key);
            if (in_array($value, [1, '1', true, 'true', 'on', 'yes'], true)) {
                return true;
            }
        }
        return false;
    }

    private function hasOutstandingUseToggles(Request $request): bool
    {
        foreach ([
            'use_default_report', 'use_default', 'use_date',
            'use_customer', 'use_party', 'use_city',
            'use_payment_mode', 'use_mode', 'use_weight', 'use_amount',
        ] as $key) {
            if ($request->has($key)) {
                return true;
            }
        }
        return false;
    }

    private function outstandingAmountTotals(Collection $rows): array
    {
        $amountIn = (float) $rows->sum(fn($r) => (float) ($r->received_amount ?? 0));
        $amountOut = (float) $rows->sum(fn($r) => (float) ($r->paid_amount ?? 0));
        $totalAmount = (float) $rows->sum(fn($r) => (float) ($r->net_total ?? 0));
        $pendingAmount = (float) $rows->sum(function ($r) {
            $received = (float) ($r->received_amount ?? 0);
            $out = (float) ($r->paid_amount ?? 0);
            return max(0, (float) ($r->net_total ?? 0) - ($received - $out));
        });

        return [
            'voucher_count' => (int) $rows->count(),
            'gross_weight' => (float) $rows->sum(fn($r) => (float) ($r->sum_gross_weight ?? 0)),
            'net_weight' => (float) $rows->sum(fn($r) => (float) ($r->sum_net_weight ?? 0)),
            'total_amount' => $totalAmount,
            'amount_in' => $amountIn,
            'amount_out' => $amountOut,
            'pending_amount' => $pendingAmount,
        ];
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
                ->get(['ai.id as approval_item_id', 'ah.id as approval_id', 'ah.approval_no', 'ah.approval_date', 'ai.status'])
                ->map(function ($r) {
                    $date = $r->approval_date ? Carbon::parse($r->approval_date)->format('d-m-Y') : '-';
                    $status = $r->status ?: '-';
                    return [
                        'id' => (int) $r->approval_id,
                        'approval_item_id' => (int) $r->approval_item_id,
                        'label' => "{$r->approval_no} ({$date}) [{$status}]",
                        'status' => $status,
                    ];
                })
                ->values()
                ->all();

            $approvalItemIds = collect($approvalHistory)
                ->pluck('approval_item_id')
                ->filter()
                ->values()
                ->all();

            $saleHistory = DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->where('s.company_id', $company->id)
                ->where(function ($q) use ($set, $approvalItemIds) {
                    $q->where('si.itemset_id', $set->id);
                    if (!empty($approvalItemIds) && Schema::hasColumn('sale_items', 'approval_item_id')) {
                        $q->orWhereIn('si.approval_item_id', $approvalItemIds);
                    }
                })
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

            $hasPendingApproval = collect($approvalHistory)
                ->contains(fn($row) => strtolower((string) ($row['status'] ?? '')) === 'pending');

            $currentStatus = 'In Stock';
            if (!empty($returnHistory)) {
                $currentStatus = 'Returned';
            } elseif (!empty($saleHistory)) {
                $currentStatus = 'Sold';
            } elseif ($hasPendingApproval) {
                $currentStatus = 'Approval';
            } elseif ((int) ($set->is_sold ?? 0) === 1) {
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
