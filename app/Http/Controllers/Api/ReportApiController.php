<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalHeader;
use App\Models\Company;
use App\Models\Customer;
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
    public function stockPositionDetails(Request $request, string $format = 'json')
    {
        $request->validate([
            'item_id' => 'required|integer|min:1',
            'stock_type' => 'nullable|in:all,finished_item,customer_received,raw_material,scrap,repair',
            'customer_id' => 'nullable|integer|min:1',
            'image_status' => 'nullable|in:all,with,without',
            'report_branches' => 'sometimes|string',
        ]);
        if (!$request->filled('stock_type')) $request->merge(['stock_type' => 'all']);
        $company = $request->user()->company;
        abort_unless($company, 403);
        \App\Services\ReportBranches::ids($company, $request);
        $controller = app(\App\Http\Controllers\Company\ReportController::class);
        if ($format === 'pdf') return $controller->stockPositionDetailsPdf($request, $company->slug);
        if ($format === 'excel') return $controller->stockPositionDetailsExcel($request, $company->slug);
        $data = $controller->stockPositionDetails($request, $company->slug)->getData();
        $rows = $data['rows']->map(function ($row) {
            $item = (array) $row;
            $item['branch_name'] = \App\Services\ReportBranches::name($row->company_id);
            return $item;
        })->values();
        return response()->json([
            'success' => true,
            'item_name' => $data['itemName'],
            'stock_type_name' => $data['stockTypeName'],
            'customer_name' => $data['customerName'],
            'image_counts' => $request->attributes->get('stock_image_counts', ['with_image'=>0, 'without_image'=>0]),
            'summary' => $data['summary'],
            'count' => $rows->count(),
            'data' => $rows,
        ]);
    }

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
        $company = Company::find($companyId);
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
                    'stock_type' => $row->stock_type,
                    'stock_type_name' => $row->stock_type_name,
                    'customer_id' => $row->customer_id ? (int) $row->customer_id : null,
                    'customer_name' => $row->customer_name ?? '-',
                    'qty_pcs' => (int) ($row->qty_pcs ?? 0),
                    'with_image' => (int)$row->with_image,
                    'without_image' => max(0, (int)$row->qty_pcs - (int)$row->with_image),
                    'gross_weight' => (float) ($row->gross_weight ?? 0),
                    'less_weight' => (float) ($row->less_weight ?? 0),
                    'net_weight' => (float) ($row->net_weight ?? 0),
                    'fine_weight' => (float) ($row->fine_weight ?? 0),
                    'labour_amount' => (float) ($row->labour_amount ?? 0),
                    'other_amount' => (float) ($row->other_amount ?? 0),
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('Y-m-d H:i:s') : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Stock position fetched successfully.',
            'supplier_balances' => \App\Services\SupplierMetalBalances::rows([$companyId], $request->filled('supplier_id') ? (int)$request->supplier_id : null),
            'count' => $rows->count(),
            'summary' => collect(['qty_pcs','gross_weight','less_weight','net_weight','fine_weight','with_image','without_image'])->mapWithKeys(fn ($key) => [$key=>$rows->sum($key)]),
            'data' => $rows,
        ]);
    }

    public function stockPositionExcel(Request $request): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->stockPositionBaseQuery($request, $companyId)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Item', 'Stock Type', 'Party', 'Qty Pcs', 'Gross Wt', 'Less Wt', 'Net Wt', 'Fine Wt', 'Labour Amt', 'Other Amt', 'With image', 'Without image']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->item_name,
                    $r->stock_type_name,
                    $r->customer_name,
                    (int) ($r->qty_pcs ?? 0),
                    number_format((float) ($r->gross_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->less_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->net_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->fine_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->labour_amount ?? 0), 2, '.', ''),
                    number_format((float) ($r->other_amount ?? 0), 2, '.', ''),
                    (int)$r->with_image, max(0, (int)$r->qty_pcs - (int)$r->with_image),
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
        $company = Company::find($companyId);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }
        $rows = $this->stockPositionBaseQuery($request, $companyId)->get();

        $summary = collect(['qty_pcs','gross_weight','other_weight','net_weight','fine_weight','with_image'])->mapWithKeys(fn ($key) => [$key=>$rows->sum($key)])->all();
        $summary['without_image'] = $rows->sum(fn ($row) => max(0, (int)$row->qty_pcs - (int)$row->with_image));
        return Pdf::loadView('company.reports.pdf.stock_position', compact('company', 'rows', 'summary'))
            ->setPaper('a4', 'portrait')
            ->download('stock_position_report.pdf');
    }

    public function workerLoss(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        if ($request->input('mode') === 'ledger') {
            return response()->json(['success' => true, 'data' => app(\App\Services\WorkerMetalLedger::class)->report(\App\Services\ReportBranches::ids(Company::findOrFail($companyId), $request), $request)]);
        }
        $rows = $this->workerLossBaseQuery($request, $companyId)
            ->get()
            ->map(function ($row) {
                $processDateTime = $row->process_datetime ? Carbon::parse($row->process_datetime) : null;

                return [
                    'stage' => (string) ($row->stage ?: '-'),
                    'company_id' => (int) $row->company_id,
                    'branch_name' => \App\Services\ReportBranches::name($row->company_id),
                    'worker_id' => $row->worker_id ? (int) $row->worker_id : null,
                    'worker_name' => (string) ($row->worker_name ?: '-'),
                    'voucher_id' => $row->voucher_id ? (int) $row->voucher_id : null,
                    'voucher_no' => (string) ($row->voucher_no ?: '-'),
                    'buch_no' => (string) ($row->buch_no ?: '-'),
                    'source_wt' => (float) ($row->source_wt ?? 0),
                    'receive_wt' => (float) ($row->receive_wt ?? 0),
                    'bhuko' => (float) ($row->bhuko ?? 0),
                    'loss' => (float) ($row->loss ?? 0),
                    'process_datetime' => $processDateTime?->format('Y-m-d H:i:s'),
                    'process_datetime_fmt' => $processDateTime?->format('d-m-Y h:i A') ?? '-',
                ];
            })
            ->values();

        $summary = $this->workerLossSummary($request, $companyId);

        return response()->json([
            'success' => true,
            'message' => 'Worker loss report fetched successfully.',
            'count' => $rows->count(),
            'filters' => [
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'worker_id' => $request->input('worker_id'),
                'stage' => $request->input('stage'),
                'voucher_no' => $request->input('voucher_no'),
                'loss_type' => $request->input('loss_type'),
                'only_loss' => $this->truthyApiValue($request->input('only_loss')),
            ],
            'totals' => $this->workerLossTotals($request, $companyId),
            'worker_summary' => $summary['workers'],
            'stage_summary' => $summary['stages'],
            'data' => $rows,
        ]);
    }

    public function workerLossSuggest(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $term = trim((string) $request->get('q', ''));
        $limit = max(1, min((int) $request->get('limit', 12), 50));

        $filterRequest = new Request($request->except(['q', 'limit', 'voucher_no']));
        $baseQuery = $this->workerLossBaseQuery($filterRequest, $companyId);
        $rows = DB::query()
            ->fromSub($baseQuery, 'loss_rows')
            ->when($term !== '', fn($q) => $q->where('voucher_no', 'like', "%{$term}%"))
            ->selectRaw('voucher_id, voucher_no, MAX(process_datetime) as latest_process_datetime')
            ->groupBy('voucher_id', 'voucher_no')
            ->orderByDesc('latest_process_datetime')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->voucher_id,
                    'voucher_no' => $row->voucher_no,
                    'date_time' => $row->latest_process_datetime
                        ? Carbon::parse($row->latest_process_datetime)->format('d-m-Y h:i A')
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function workerLossExcel(Request $request)
    {
        $company = Company::findOrFail((int) $request->user()->company_id);
        \App\Services\ReportBranches::ids($company, $request);
        $csv = app(\App\Http\Controllers\Company\ReportController::class)
            ->workerLossExcel($request, $company->slug);
        ob_start();
        try {
            $csv->sendContent();
            $content = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $stream = fopen('php://temp', 'w+');
        try {
            fwrite($stream, $content);
            rewind($stream);
            $rows = [];
            while (($row = fgetcsv($stream)) !== false) $rows[] = $row;
            return \App\Services\ReportXlsx::download($rows, 'worker_loss_report.xlsx', 'Worker Loss');
        } finally {
            fclose($stream);
        }
    }
    public function workerLossPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        if ($request->input('mode') === 'ledger') {
            $company = Company::findOrFail($companyId);
            $ledger = app(\App\Services\WorkerMetalLedger::class)->report(\App\Services\ReportBranches::ids(Company::findOrFail($companyId), $request), $request);
            return Pdf::loadView('company.reports.pdf.worker_metal_ledger', compact('company', 'ledger'))->setPaper('a4', 'portrait')->download('worker_metal_ledger.pdf');
        }
        $company = Company::find($companyId);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }

        $rows = $this->workerLossBaseQuery($request, $companyId)->get();
        $totals = $this->workerLossTotals($request, $companyId);
        $summary = $this->workerLossSummary($request, $companyId);

        return Pdf::loadView('company.reports.pdf.worker_loss', compact('company', 'rows', 'totals', 'summary', 'request'))
            ->setPaper('a4', 'landscape')
            ->download('worker_loss_report.pdf');
    }

    public function approvalOutstanding(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $totals = $this->approvalOutstandingTotalsDetailed($request, $companyId);
        $approvalRows = $this->approvalOutstandingBaseQuery($request, $companyId)
            ->latest('approval_date')
            ->get();
        $voucherTotals = $this->approvalOutstandingVoucherTotals(
            $companyId,
            $approvalRows->pluck('id')->map(fn($id) => (int) $id)->all()
        );

        $rows = $approvalRows
            ->map(function ($row) use ($voucherTotals) {
                $rowTotals = $voucherTotals[(int) $row->id] ?? [];

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
                    'pending_gross_weight' => (float) ($row->pending_gross_weight ?? 0),
                    'pending_other_weight' => (float) ($row->pending_other_weight ?? 0),
                    'pending_net_weight' => (float) ($row->pending_net_weight ?? 0),
                    'pending_purity' => (float) ($row->pending_purity ?? 0),
                    'pending_waste_percent' => (float) ($row->pending_waste_percent ?? 0),
                    'pending_metal_rate' => (float) ($row->pending_metal_rate ?? 0),
                    'pending_labour_rate' => (float) ($row->pending_labour_rate ?? 0),
                    'pending_other_amount' => (float) ($row->pending_other_amount ?? 0),
                    'pending_total_amount' => (float) ($row->pending_total_amount ?? 0),
                    'qty_pcs' => (int) ($rowTotals['qty_pcs'] ?? 0),
                    'gross_weight' => (float) ($rowTotals['gross_weight'] ?? 0),
                    'other_weight' => (float) ($rowTotals['other_weight'] ?? 0),
                    'net_weight' => (float) ($rowTotals['net_weight'] ?? 0),
                    'fine_weight' => (float) ($rowTotals['fine_weight'] ?? 0),
                    'metal_amount' => (float) ($rowTotals['metal_amount'] ?? 0),
                    'labour_amount' => (float) ($rowTotals['labour_amount'] ?? 0),
                    'other_amount' => (float) ($rowTotals['other_amount'] ?? 0),
                    'net_total' => (float) ($rowTotals['net_total'] ?? 0),
                ];
            })
            ->values();

        $visibleColumns = $this->approvalOutstandingVisibleColumns($request);

        return response()->json([
            'success' => true,
            'message' => 'Approval outstanding fetched successfully.',
            'count' => $rows->count(),
            'columns' => array_values($visibleColumns),
            'totals' => $totals,
            'data' => $this->filterApiRowsForColumns($request, $rows, $visibleColumns, [
                'date' => 'approval_date_fmt',
                'customer' => 'customer_name',
                'pending_pcs' => 'pending_items',
                'gross_weight' => 'pending_gross_weight',
                'other_weight' => 'pending_other_weight',
                'net_weight' => 'pending_net_weight',
                'purity' => 'pending_purity',
                'waste_percent' => 'pending_waste_percent',
                'metal_rate' => 'pending_metal_rate',
                'labour_rate' => 'pending_labour_rate',
                'other_amount' => 'pending_other_amount',
                'pending_amount' => 'pending_total_amount',
            ]),
        ]);
    }

    public function approvalOutstandingDetails(Request $request, ApprovalHeader $approval)
    {
        $companyId = (int) $request->user()->company_id;

        if ((int) $approval->company_id !== $companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Approval voucher not found for this company.',
            ], 404);
        }

        $payload = $this->approvalOutstandingDetailsPayload($companyId, $approval);

        $visibleColumns = $this->approvalOutstandingDetailsVisibleColumns($request);
        $payload['data'] = $this->filterApiRowsForColumns($request, $payload['data'], $visibleColumns, [
            'item' => 'item_name',
            'pending_amount' => 'total_amount',
        ], []);

        return response()->json([
            'success' => true,
            'message' => 'Approval outstanding details fetched successfully.',
            'columns' => array_values($visibleColumns),
        ] + $payload);
    }

    public function approvalOutstandingExcel(Request $request): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->approvalOutstandingBaseQuery($request, $companyId)->latest('approval_date')->get();
        $summary = $this->approvalOutstandingTotals($rows);
        $visibleColumns = $this->approvalOutstandingVisibleColumns($request);

        return response()->streamDownload(function () use ($rows, $summary, $visibleColumns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_column($visibleColumns, 'label'));
            foreach ($rows as $r) {
                fputcsv($out, $this->approvalOutstandingExportRow($r, $visibleColumns));
            }
            fputcsv($out, $this->reportTotalRow($summary, $visibleColumns));
            fclose($out);
        }, 'approval_outstanding_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function approvalOutstandingPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::find($companyId);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }
        $rows = $this->approvalOutstandingBaseQuery($request, $companyId)->latest('approval_date')->get();
        $summary = $this->approvalOutstandingTotals($rows);
        $visibleColumns = $this->approvalOutstandingVisibleColumns($request);

        return Pdf::loadView('company.reports.pdf.approval_outstanding', compact('company', 'rows', 'summary', 'visibleColumns'))
            ->setPaper('a4', 'landscape')
            ->download('approval_outstanding_report.pdf');
    }

    public function approvalOutstandingDetailsExcel(Request $request, ApprovalHeader $approval): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $payload = $this->approvalOutstandingDetailsPayload($companyId, $approval);
        if (!$payload) {
            abort(404, 'Approval voucher not found for this company.');
        }
        $columns = $this->approvalOutstandingDetailsVisibleColumns($request);

        return response()->streamDownload(function () use ($payload, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [...array_column($columns, 'label'), 'Company / Branch']);
            $branchName = (string) $payload['approval']['branch_name'];
            if (preg_match('/^[=+@\-\t\r\n]/', $branchName)) $branchName = "'".$branchName;
            foreach ($payload['data'] as $item) {
                fputcsv($out, [...$this->approvalOutstandingDetailsExportRow($item, $columns), $branchName]);
            }
            fputcsv($out, [...$this->reportTotalRow($payload['summary'], $columns), '']);
            fclose($out);
        }, 'approval_outstanding_details.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function approvalOutstandingDetailsPdf(Request $request, ApprovalHeader $approval)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::find($companyId) ?: (object) ['name' => 'Company', 'company_name' => 'Company'];
        $payload = $this->approvalOutstandingDetailsPayload($companyId, $approval);
        if (!$payload) {
            abort(404, 'Approval voucher not found for this company.');
        }
        $columns = $this->approvalOutstandingDetailsVisibleColumns($request);

        return Pdf::loadView('company.reports.pdf.approval_outstanding_details', [
            'company' => $company,
            'approval' => $payload['approval'],
            'summary' => $payload['summary'],
            'items' => $payload['data'],
            'columns' => $columns,
        ])->setPaper('a4', 'portrait')->download('approval_outstanding_details.pdf');
    }

    public function outstandingAmount(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $rows = $this->outstandingAmountBaseQuery($request, $companyId)
            ->latest('id')
            ->get();

        $summary = $this->outstandingAmountTotals($rows);

        $data = $rows->map(function ($row) {
            $received = (float) ($row->received_amount ?? 0);
            $amountOut = (float) ($row->paid_amount ?? 0);
            $totalAmount = (float) ($row->net_total ?? 0);
            $pending = max(0, $totalAmount - $row->appliedReceivedAmount());
            $metalDebit = $this->outstandingMetalDebit($row);

            return [
                'id' => (int) $row->id,
                'voucher_no' => (string) ($row->voucher_no ?? ''),
                'sale_date' => optional($row->sale_date)?->format('Y-m-d'),
                'sale_date_fmt' => optional($row->sale_date)?->format('d-m-Y') ?? '-',
                'party' => (string) (optional($row->customer)->name ?? '-'),
                'city' => (string) (optional($row->customer)->city ?? '-'),
                'payment_mode' => (string) ($row->payment_mode ?? '-'),
                'gross_weight' => (float) ($row->sum_gross_weight ?? 0),
                'net_weight' => (float) ($row->sum_net_weight ?? 0),
                'fine_weight' => (float) ($row->sum_fine_weight ?? 0),
                'metal_amount' => (float) ($row->sum_metal_amount ?? 0),
                'labour_amount' => (float) ($row->sum_labour_amount ?? 0),
                'metal_debit' => $metalDebit,
                'total_amount' => $totalAmount,
                'amount_in' => $received,
                'amount_out' => $amountOut,
                'pending' => $pending,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Outstanding amount report fetched successfully.',
            'count' => $data->count(),
            'filters' => [
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'customer_id' => $request->input('customer_id'),
                'city' => $request->input('city'),
                'payment_mode' => $request->input('payment_mode'),
                'weight_from' => $request->input('weight_from'),
                'weight_to' => $request->input('weight_to'),
                'amount_from' => $request->input('amount_from'),
                'amount_to' => $request->input('amount_to'),
            ],
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    public function outstandingAmountExcel(Request $request): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->outstandingAmountBaseQuery($request, $companyId)->latest('id')->get();
        $summary = $this->outstandingAmountTotals($rows);

        return response()->streamDownload(function () use ($rows, $summary) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Voucher No', 'Date', 'Party', 'City', 'Payment Mode', 'Gross Wt', 'Net Wt', 'Fine Wt', 'Metal Amt', 'Labour Amt', 'Metal Dr', 'Total Amount', 'Amount In', 'Amount Out', 'Pending']);
            foreach ($rows as $r) {
                $received = (float) ($r->received_amount ?? 0);
                $outAmt = (float) ($r->paid_amount ?? 0);
                $pending = max(0, (float) ($r->net_total ?? 0) - $r->appliedReceivedAmount());
                fputcsv($out, [
                    $r->voucher_no,
                    optional($r->sale_date)?->format('d-m-Y') ?? '-',
                    optional($r->customer)->name ?? '-',
                    optional($r->customer)->city ?? '-',
                    $r->payment_mode ?? '-',
                    number_format((float) ($r->sum_gross_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->sum_net_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->sum_fine_weight ?? 0), 3, '.', ''),
                    number_format((float) ($r->sum_metal_amount ?? 0), 2, '.', ''),
                    number_format((float) ($r->sum_labour_amount ?? 0), 2, '.', ''),
                    number_format($this->outstandingMetalDebit($r), 3, '.', ''),
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
                number_format((float) ($summary['fine_weight'] ?? 0), 3, '.', ''),
                number_format((float) ($summary['metal_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($summary['labour_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($summary['metal_debit'] ?? 0), 3, '.', ''),
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

    public function outstandingAmountPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::find($companyId);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }

        $rows = $this->outstandingAmountBaseQuery($request, $companyId)->latest('id')->get();
        $summary = $this->outstandingAmountTotals($rows);
        $selectedCustomer = $request->filled('customer_id')
            ? Customer::where('company_id', $companyId)->find((int) $request->customer_id)
            : null;
        $visible = [
            'default' => $this->isToggleEnabled($request, ['use_default_report', 'use_default']),
            'date' => $this->isToggleEnabled($request, ['use_date']),
            'party' => $this->isToggleEnabled($request, ['use_customer', 'use_party']),
            'city' => $this->isToggleEnabled($request, ['use_city']),
            'mode' => $this->isToggleEnabled($request, ['use_payment_mode', 'use_mode']),
            'weight' => $this->isToggleEnabled($request, ['use_weight']),
            'amount' => $this->isToggleEnabled($request, ['use_amount']),
        ];

        if (!$visible['default'] && !$visible['date'] && !$visible['party'] && !$visible['city'] && !$visible['mode'] && !$visible['weight'] && !$visible['amount']) {
            $visible['default'] = true;
        }

        return Pdf::loadView('company.reports.pdf.outstanding_amount', compact('company', 'rows', 'summary', 'visible', 'selectedCustomer'))
            ->setPaper('a4', 'landscape')
            ->download('outstanding_amount_report.pdf');
    }

    public function outstandingAmountLedgerPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::find($companyId);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }

        $rows = $this->outstandingAmountBaseQuery($request, $companyId)
            ->latest('sale_date')
            ->latest('id')
            ->get();
        $summary = $this->outstandingAmountTotals($rows);
        $selectedCustomer = $request->filled('customer_id')
            ? Customer::where('company_id', $companyId)->find((int) $request->customer_id)
            : null;

        return Pdf::loadView('company.reports.pdf.outstanding_amount_ledger', compact('company', 'rows', 'summary', 'selectedCustomer'))
            ->setPaper('a4', 'landscape')
            ->download('outstanding_amount_ledger_report.pdf');
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
                    'other_weight' => (float) ($sale->total_other_weight ?? 0),
                    'net_weight' => (float) ($sale->total_net_weight ?? 0),
                    'purity' => (float) ($sale->total_purity ?? 0),
                    'waste_percent' => (float) ($sale->total_waste_percent ?? 0),
                    'fine_weight' => (float) ($sale->total_fine_weight ?? 0),
                    'metal_rate' => (float) ($sale->total_metal_rate ?? 0),
                    'metal_amount' => (float) ($sale->total_metal_amount ?? 0),
                    'labour_rate' => (float) ($sale->total_labour_rate ?? 0),
                    'labour_amount' => (float) ($sale->total_labour_amount ?? 0),
                    'other_amount' => (float) ($sale->total_other_amount ?? 0),
                    'net_total' => (float) ($sale->net_total ?? 0),
                ];
            })
            ->values();

        $visibleColumns = $this->salesSummaryVisibleColumns($request);

        return response()->json([
            'success' => true,
            'message' => 'Sales summary fetched successfully.',
            'count' => $rows->count(),
            'columns' => array_values($visibleColumns),
            'totals' => $this->salesSummaryTotals($request, $companyId),
            'data' => $this->filterApiRowsForColumns($request, $rows, $visibleColumns, [
                'date' => 'sale_date_fmt',
                'customer' => 'customer_name',
            ]),
        ]);
    }

    public function salesSummaryExcel(Request $request): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->salesSummaryBaseQuery($request, $companyId)->latest()->get();
        $totals = $this->salesSummaryTotals($request, $companyId);
        $visibleColumns = $this->salesSummaryVisibleColumns($request);

        return response()->streamDownload(function () use ($rows, $totals, $visibleColumns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_column($visibleColumns, 'label'));

            foreach ($rows as $r) {
                fputcsv($out, $this->salesSummaryExportRow($r, $visibleColumns));
            }

            fputcsv($out, $this->reportTotalRow($totals, $visibleColumns));

            fclose($out);
        }, 'sales_summary_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function salesSummaryPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::find($companyId);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }

        $rows = $this->salesSummaryBaseQuery($request, $companyId)->latest()->get();
        $totals = $this->salesSummaryTotals($request, $companyId);
        $visibleColumns = $this->salesSummaryVisibleColumns($request);

        return Pdf::loadView('company.reports.pdf.sales_summary', compact('company', 'rows', 'totals', 'visibleColumns'))
            ->setPaper('a4', 'landscape')
            ->download('sales_summary_report.pdf');
    }

    public function salesSummaryDetails(Request $request, Sale $sale)
    {
        $companyId = (int) $request->user()->company_id;
        $payload = $this->salesSummaryDetailsPayload($companyId, $sale);
        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'Sale voucher not found for this company.',
            ], 404);
        }

        $visibleColumns = $this->salesSummaryDetailsVisibleColumns($request);
        $payload['data'] = $this->filterApiRowsForColumns($request, $payload['data'], $visibleColumns, [
            'item' => 'item_name',
        ], []);

        return response()->json([
            'success' => true,
            'message' => 'Sale details fetched successfully.',
            'columns' => array_values($visibleColumns),
        ] + $payload);
    }

    public function salesSummaryDetailsExcel(Request $request, Sale $sale): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $payload = $this->salesSummaryDetailsPayload($companyId, $sale);
        if (!$payload) {
            abort(404, 'Sale voucher not found for this company.');
        }
        $columns = $this->salesSummaryDetailsVisibleColumns($request);

        return response()->streamDownload(function () use ($payload, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [...array_column($columns, 'label'), 'Company / Branch']);
            foreach ($payload['data'] as $item) {
                $branchName = (string) $payload['sale']['branch_name'];
                if (preg_match('/^[=+@\-\t\r\n]/', $branchName)) $branchName = "'".$branchName;
                fputcsv($out, [...$this->salesSummaryDetailsExportRow($item, $columns), $branchName]);
            }
            fputcsv($out, [...$this->reportTotalRow($payload['summary'], $columns), '']);
            fclose($out);
        }, 'sales_summary_details.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function salesSummaryDetailsPdf(Request $request, Sale $sale)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::find($companyId) ?: (object) ['name' => 'Company', 'company_name' => 'Company'];
        $payload = $this->salesSummaryDetailsPayload($companyId, $sale);
        if (!$payload) {
            abort(404, 'Sale voucher not found for this company.');
        }
        $columns = $this->salesSummaryDetailsVisibleColumns($request);

        return Pdf::loadView('company.reports.pdf.sales_summary_details', [
            'company' => $company,
            'sale' => $payload['sale'],
            'summary' => $payload['summary'],
            'items' => $payload['data'],
            'columns' => $columns,
        ])->setPaper('a4', 'portrait')->download('sales_summary_details.pdf');
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
        $companyIds = \App\Services\ReportBranches::ids(Company::findOrFail($user->company_id), $request);
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
            ->whereIn('item_sets.company_id', $companyIds)
            ->where(function ($q) use ($like) {
                $q->where('item_sets.qr_code', 'like', $like)
                    ->orWhere('item_sets.barcode', 'like', $like)
                    ->orWhere('item_sets.HUID', 'like', $like);
            })
            ->orderByDesc('item_sets.id')
            ->limit($limit * 3)
            ->get([
                'item_sets.company_id',
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
                    'company_id' => (int) $row->company_id,
                    'branch_name' => \App\Services\ReportBranches::name($row->company_id),
                    'code' => $val,
                    'item_name' => $row->item_name ?: '',
                    'type' => $field,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $codes->unique(fn ($row) => $row['company_id'].'|'.$row['code'])->values()->take($limit)->values(),
        ]);
    }

    public function barcodeHistoryExcel(Request $request)
    {
        $company = Company::findOrFail($request->user()->company_id);
        $request->merge(['code' => trim((string) ($request->code ?? $request->barcode ?? $request->qr_code ?? $request->huid ?? ''))]);
        return app(\App\Http\Controllers\Company\ReportController::class)->barcodeHistoryExcel($request, $company->slug);
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

        $company = Company::find((int) $user->company_id);
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
        $companyIds = \App\Services\ReportBranches::ids(Company::findOrFail($companyId), $request);
        $slug = trim((string) $request->get('slug', ''));
        if ($slug === '') {
            $slug = (string) (Company::where('id', $companyId)->value('slug') ?? '');
        }

        $itemSets = ItemSet::query()
            ->leftJoin('items', 'items.id', '=', 'item_sets.item_id')
            ->whereIn('item_sets.company_id', $companyIds)
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
            $companyId = (int) $set->company_id;
            $slug = (string) Company::whereKey($companyId)->value('slug');
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
                ->get(['ai.id as approval_item_id', 'ah.id', 'ah.approval_no', 'ah.approval_date', 'ai.status'])
                ->map(function ($r) {
                    $status = (string) ($r->status ?? '');
                    return [
                        'id' => (int) $r->id,
                        'approval_item_id' => (int) $r->approval_item_id,
                        'label' => $r->approval_no . ' (' . ( $r->approval_date ? Carbon::parse($r->approval_date)->format('d-m-Y') : '-') . ')' . ($status !== '' ? ' [' . $status . ']' : ''),
                        'status' => $status,
                    ];
                })
                ->values();

            $approvalItemIds = $approvalHistory
                ->pluck('approval_item_id')
                ->filter()
                ->values()
                ->all();

            $saleHistory = DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->where('s.company_id', $companyId)
                ->where(function ($q) use ($set, $approvalItemIds) {
                    $q->where('si.itemset_id', $set->id);
                    if (!empty($approvalItemIds) && Schema::hasColumn('sale_items', 'approval_item_id')) {
                        $q->orWhereIn('si.approval_item_id', $approvalItemIds);
                    }
                })
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

            $hasPendingApproval = $approvalHistory
                ->contains(fn($row) => strtolower((string) ($row['status'] ?? '')) === 'pending');

            $currentStatus = 'in_stock';
            if ($returnHistory->isNotEmpty()) {
                $currentStatus = 'returned';
            } elseif ($saleHistory->isNotEmpty()) {
                $currentStatus = 'sold';
            } elseif ($hasPendingApproval) {
                $currentStatus = 'approval';
            } elseif ((int) ($set->is_sold ?? 0) === 1) {
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
                'company_id' => (int) $set->company_id,
                'branch_name' => \App\Services\ReportBranches::name($set->company_id),
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

    private function requestedColumns(Request $request): array
    {
        $requested = $request->input('columns');
        if (is_string($requested)) {
            $requested = array_filter(array_map('trim', explode(',', $requested)));
        }

        return is_array($requested) ? array_values($requested) : [];
    }

    private function columnsByKeys(array $definitions, array $keys): array
    {
        $visible = [];
        foreach (array_unique($keys) as $key) {
            if (isset($definitions[$key])) {
                $visible[$key] = $definitions[$key] + ['key' => $key];
            }
        }

        return $visible;
    }

    private function visibleReportColumns(Request $request, array $definitions, array $defaultKeys): array
    {
        $requested = $this->requestedColumns($request);
        if (empty($requested)) {
            $requested = $defaultKeys;
        }

        return $this->columnsByKeys($definitions, $requested) ?: $this->columnsByKeys($definitions, $defaultKeys);
    }

    private function filterApiRowsForColumns(Request $request, Collection $rows, array $columns, array $sourceMap = [], array $always = ['id']): Collection
    {
        if (empty($this->requestedColumns($request))) {
            return $rows->values();
        }

        return $rows->map(function ($row) use ($columns, $sourceMap, $always) {
            $row = (array) $row;
            $filtered = [];

            foreach ($always as $key) {
                if (array_key_exists($key, $row)) {
                    $filtered[$key] = $row[$key];
                }
            }

            foreach (array_keys($columns) as $key) {
                $sourceKey = $sourceMap[$key] ?? $key;
                $filtered[$key] = $row[$sourceKey] ?? null;
            }

            return $filtered;
        })->values();
    }

    private function reportTotalRow(array $totals, array $columns): array
    {
        $row = [];
        $hasTotalLabel = false;
        foreach ($columns as $column) {
            if (!$hasTotalLabel) {
                $row[] = 'TOTAL';
                $hasTotalLabel = true;
                continue;
            }

            if (!isset($column['total_key'])) {
                $row[] = $column['total'] ?? '';
                continue;
            }

            $value = $totals[$column['total_key']] ?? 0;
            $decimals = (int) ($column['decimals'] ?? 0);
            $row[] = $decimals > 0
                ? number_format((float) $value, $decimals, '.', '')
                : (int) $value;
        }

        return $row;
    }

    private function approvalOutstandingColumnDefinitions(): array
    {
        return [
            'approval_no' => ['label' => 'Approval No', 'total' => 'TOTAL'],
            'date' => ['label' => 'Date', 'total' => ''],
            'customer' => ['label' => 'Customer Name', 'total' => ''],
            'status' => ['label' => 'Status', 'total' => ''],
            'pending_pcs' => ['label' => 'Pending Pcs', 'total_key' => 'pending_pcs', 'decimals' => 0],
            'gross_weight' => ['label' => 'Gross Wt', 'total_key' => 'pending_gross_weight', 'decimals' => 3],
            'other_weight' => ['label' => 'Other Wt', 'total_key' => 'pending_other_weight', 'decimals' => 3],
            'net_weight' => ['label' => 'Pending Net Wt', 'total_key' => 'pending_net_weight', 'decimals' => 3],
            'purity' => ['label' => 'Purity', 'total_key' => 'pending_purity', 'decimals' => 3],
            'waste_percent' => ['label' => 'Waste %', 'total_key' => 'pending_waste_percent', 'decimals' => 3],
            'metal_rate' => ['label' => 'Metal Rate', 'total_key' => 'pending_metal_rate', 'decimals' => 2],
            'labour_rate' => ['label' => 'Labour Rate', 'total_key' => 'pending_labour_rate', 'decimals' => 2],
            'other_amount' => ['label' => 'Other Amount', 'total_key' => 'pending_other_amount', 'decimals' => 2],
            'pending_amount' => ['label' => 'Pending Amount', 'total_key' => 'pending_amount', 'decimals' => 2],
            'remarks' => ['label' => 'Remarks', 'total' => ''],
            'created_by' => ['label' => 'Created By', 'total' => ''],
        ];
    }

    private function approvalOutstandingDetailsColumnDefinitions(): array
    {
        return [
            'approval_no' => ['label' => 'Approval No', 'total' => 'TOTAL'],
            'date' => ['label' => 'Date', 'total' => ''],
            'customer' => ['label' => 'Customer Name', 'total' => ''],
            'qr_code' => ['label' => 'QR Code', 'total' => 'TOTAL'],
            'huid' => ['label' => 'HUID', 'total' => ''],
            'item' => ['label' => 'Item', 'total' => ''],
            'status' => ['label' => 'Status', 'total' => ''],
            'gross_weight' => ['label' => 'Gross Wt', 'total_key' => 'pending_gross_weight', 'decimals' => 3],
            'other_weight' => ['label' => 'Other Wt', 'total_key' => 'pending_other_weight', 'decimals' => 3],
            'net_weight' => ['label' => 'Net Wt', 'total_key' => 'pending_net_weight', 'decimals' => 3],
            'other_amount' => ['label' => 'Other Amount', 'total_key' => 'pending_other_amount', 'decimals' => 2],
            'pending_amount' => ['label' => 'Amount', 'total_key' => 'pending_amount', 'decimals' => 2],
        ];
    }

    private function approvalOutstandingVisibleColumns(Request $request): array
    {
        return $this->visibleReportColumns($request, $this->approvalOutstandingColumnDefinitions(), [
            'approval_no', 'date', 'customer', 'status', 'pending_pcs', 'gross_weight',
            'other_weight', 'net_weight', 'purity', 'waste_percent', 'metal_rate', 'labour_rate',
            'other_amount', 'pending_amount', 'remarks', 'created_by',
        ]);
    }

    private function approvalOutstandingDetailsVisibleColumns(Request $request): array
    {
        $definitions = $this->approvalOutstandingDetailsColumnDefinitions();
        $selected = $this->requestedColumns($request);
        if (!empty($selected)) {
            return $this->columnsByKeys($definitions, array_map(function ($key) {
                return $key === 'pending_amount' ? 'pending_amount' : $key;
            }, $selected));
        }

        $visibleKeys = ['qr_code', 'huid', 'item'];

        foreach (['status', 'gross_weight', 'other_weight', 'net_weight', 'other_amount', 'pending_amount'] as $key) {
            if (empty($selected) || in_array($key, $selected, true)) {
                $visibleKeys[] = $key;
            }
        }

        return $this->columnsByKeys($definitions, $visibleKeys);
    }

    private function approvalOutstandingExportRow($row, array $visibleColumns): array
    {
        $values = [
            'approval_no' => $row->approval_no,
            'date' => optional($row->approval_date)?->format('d-m-Y'),
            'customer' => optional($row->customer)->name ?? '-',
            'status' => $row->status,
            'pending_pcs' => (int) ($row->pending_items_count ?? 0),
            'gross_weight' => number_format((float) ($row->pending_gross_weight ?? 0), 3, '.', ''),
            'other_weight' => number_format((float) ($row->pending_other_weight ?? 0), 3, '.', ''),
            'net_weight' => number_format((float) ($row->pending_net_weight ?? 0), 3, '.', ''),
            'purity' => number_format((float) ($row->pending_purity ?? 0), 3, '.', ''),
            'waste_percent' => number_format((float) ($row->pending_waste_percent ?? 0), 3, '.', ''),
            'metal_rate' => number_format((float) ($row->pending_metal_rate ?? 0), 2, '.', ''),
            'labour_rate' => number_format((float) ($row->pending_labour_rate ?? 0), 2, '.', ''),
            'other_amount' => number_format((float) ($row->pending_other_amount ?? 0), 2, '.', ''),
            'pending_amount' => number_format((float) ($row->pending_total_amount ?? 0), 2, '.', ''),
            'remarks' => $row->remarks ?? '-',
            'created_by' => optional($row->creator)->name ?? '-',
        ];

        return array_map(fn($key) => $values[$key] ?? '', array_keys($visibleColumns));
    }

    private function approvalOutstandingDetailsPayload(int $companyId, ApprovalHeader $approval): ?array
    {
        $company = Company::findOrFail($companyId);
        $allowedIds = \App\Services\ReportBranches::ids($company);
        if (!in_array((int) $approval->company_id, $allowedIds, true)) {
            return null;
        }
        $branch = Company::findOrFail($approval->company_id);

        $approval->load(['customer', 'creator']);

        $items = $approval->items()
            ->with(['itemSet.item', 'legacyItemSet.item', 'item'])
            ->where('status', 'pending')
            ->orderBy('id')
            ->get()
            ->map(function ($row) use ($approval) {
                $itemSet = $row->itemSet ?? $row->legacyItemSet;
                $item = optional($itemSet)->item ?? $row->item;
                $grossWeight = (float) ($row->gross_weight ?? optional($itemSet)->gross_weight ?? 0);
                $otherWeight = (float) ($row->other_weight ?? optional($itemSet)->other ?? 0);
                $netWeight = (float) ($row->net_weight ?? optional($itemSet)->net_weight ?? max(0, $grossWeight - $otherWeight));

                return [
                    'approval_item_id' => (int) $row->id,
                    'itemset_id' => optional($itemSet)->id,
                    'item_id' => $row->item_id ?? optional($itemSet)->item_id,
                    'approval_no' => $approval->approval_no,
                    'date' => optional($approval->approval_date)?->format('d-m-Y') ?? '-',
                    'customer' => optional($approval->customer)->name ?? '-',
                    'qr_code' => $row->qr_code ?? optional($itemSet)->qr_code,
                    'huid' => $row->huid ?? optional($itemSet)->HUID,
                    'item_name' => optional($item)->item_name,
                    'gross_weight' => $grossWeight,
                    'other_weight' => $otherWeight,
                    'net_weight' => $netWeight,
                    'purity' => (float) ($row->purity ?? optional($item)->outward_purity ?? 0),
                    'waste_percent' => (float) ($row->waste_percent ?? 0),
                    'net_purity' => (float) ($row->net_purity ?? 0),
                    'fine_weight' => (float) ($row->total_fine_weight ?? 0),
                    'metal_rate' => (float) ($row->metal_rate ?? 0),
                    'metal_amount' => (float) ($row->metal_amount ?? 0),
                    'labour_rate' => (float) ($row->labour_rate ?? optional($itemSet)->sale_labour_rate ?? optional($item)->labour_rate ?? 0),
                    'labour_amount' => (float) ($row->labour_amount ?? 0),
                    'other_amount' => (float) ($row->other_amount ?? optional($itemSet)->sale_other ?? 0),
                    'total_amount' => (float) ($row->total_amount ?? 0),
                    'status' => $row->status,
                    'remarks' => $row->remarks ?? '',
                ];
            })
            ->values();

        return [
            'approval' => [
                'company_id' => (int) $branch->id,
                'branch_name' => $branch->name,
                'id' => (int) $approval->id,
                'approval_no' => $approval->approval_no,
                'approval_date' => optional($approval->approval_date)?->format('Y-m-d'),
                'approval_date_fmt' => optional($approval->approval_date)?->format('d-m-Y') ?? '-',
                'customer_id' => (int) ($approval->customer_id ?? 0),
                'customer_name' => optional($approval->customer)->name ?? '-',
                'status' => $approval->status,
                'remarks' => $approval->remarks ?? '-',
                'created_by' => optional($approval->creator)->name ?? '-',
            ],
            'summary' => [
                'pending_pcs' => (int) $items->count(),
                'pending_gross_weight' => (float) $items->sum('gross_weight'),
                'pending_other_weight' => (float) $items->sum('other_weight'),
                'pending_net_weight' => (float) $items->sum('net_weight'),
                'pending_other_amount' => (float) $items->sum('other_amount'),
                'pending_amount' => (float) $items->sum('total_amount'),
            ],
            'data' => $items,
        ];
    }

    private function approvalOutstandingDetailsExportRow(array $item, array $columns): array
    {
        $values = [
            'approval_no' => $item['approval_no'] ?? '-',
            'date' => $item['date'] ?? '-',
            'customer' => $item['customer'] ?? '-',
            'qr_code' => $item['qr_code'] ?? '-',
            'huid' => $item['huid'] ?? '-',
            'item' => $item['item_name'] ?? '-',
            'status' => $item['status'] ?? '-',
            'gross_weight' => number_format((float) ($item['gross_weight'] ?? 0), 3, '.', ''),
            'other_weight' => number_format((float) ($item['other_weight'] ?? 0), 3, '.', ''),
            'net_weight' => number_format((float) ($item['net_weight'] ?? 0), 3, '.', ''),
            'other_amount' => number_format((float) ($item['other_amount'] ?? 0), 2, '.', ''),
            'pending_amount' => number_format((float) ($item['total_amount'] ?? 0), 2, '.', ''),
        ];

        return array_map(fn($key) => $values[$key] ?? '', array_keys($columns));
    }

    private function salesSummaryColumnDefinitions(): array
    {
        return [
            'voucher_no' => ['label' => 'Voucher No', 'total' => 'TOTAL'],
            'date' => ['label' => 'Date', 'total' => ''],
            'customer' => ['label' => 'Customer Name', 'total' => ''],
            'qty_pcs' => ['label' => 'Qty', 'total_key' => 'qty_pcs', 'decimals' => 0],
            'gross_weight' => ['label' => 'Gross Wt', 'total_key' => 'gross_weight', 'decimals' => 3],
            'other_weight' => ['label' => 'Other Wt', 'total_key' => 'other_weight', 'decimals' => 3],
            'net_weight' => ['label' => 'Net Wt', 'total_key' => 'net_weight', 'decimals' => 3],
            'purity' => ['label' => 'Purity', 'total_key' => 'purity', 'decimals' => 3],
            'waste_percent' => ['label' => 'Waste %', 'total_key' => 'waste_percent', 'decimals' => 3],
            'fine_weight' => ['label' => 'Fine Wt', 'total_key' => 'fine_weight', 'decimals' => 3],
            'metal_rate' => ['label' => 'Metal Rate', 'total_key' => 'metal_rate', 'decimals' => 2],
            'metal_amount' => ['label' => 'Metal Amt', 'total_key' => 'metal_amount', 'decimals' => 2],
            'labour_rate' => ['label' => 'Labour Rate', 'total_key' => 'labour_rate', 'decimals' => 2],
            'labour_amount' => ['label' => 'Labour Amt', 'total_key' => 'labour_amount', 'decimals' => 2],
            'other_amount' => ['label' => 'Other Amt', 'total_key' => 'other_amount', 'decimals' => 2],
            'net_total' => ['label' => 'Total', 'total_key' => 'net_total', 'decimals' => 2],
            'remarks' => ['label' => 'Remarks', 'total' => ''],
            'created_by' => ['label' => 'Created By', 'total' => ''],
        ];
    }

    private function salesSummaryDetailsColumnDefinitions(): array
    {
        return [
            'voucher_no' => ['label' => 'Voucher No', 'total' => 'TOTAL'],
            'date' => ['label' => 'Date', 'total' => ''],
            'customer' => ['label' => 'Customer Name', 'total' => ''],
            'label' => ['label' => 'Label', 'total' => 'TOTAL'],
            'huid' => ['label' => 'HUID', 'total' => ''],
            'item' => ['label' => 'Item', 'total' => ''],
            'item_name' => ['label' => 'Item', 'total' => ''],
            'qty_pcs' => ['label' => 'Qty', 'total_key' => 'qty_pcs', 'decimals' => 0],
            'gross_weight' => ['label' => 'Gross Wt', 'total_key' => 'gross_weight', 'decimals' => 3],
            'other_weight' => ['label' => 'Other Wt', 'total_key' => 'other_weight', 'decimals' => 3],
            'net_weight' => ['label' => 'Net Wt', 'total_key' => 'net_weight', 'decimals' => 3],
            'purity' => ['label' => 'Purity', 'total' => ''],
            'waste_percent' => ['label' => 'Waste %', 'total' => ''],
            'net_purity' => ['label' => 'Net Purity', 'total' => ''],
            'fine_weight' => ['label' => 'Fine Wt', 'total_key' => 'fine_weight', 'decimals' => 3],
            'metal_rate' => ['label' => 'Metal Rate', 'total' => ''],
            'metal_amount' => ['label' => 'Metal Amt', 'total_key' => 'metal_amount', 'decimals' => 2],
            'labour_rate' => ['label' => 'Labour Rate', 'total' => ''],
            'labour_amount' => ['label' => 'Labour Amt', 'total_key' => 'labour_amount', 'decimals' => 2],
            'other_amount' => ['label' => 'Other Amt', 'total_key' => 'other_amount', 'decimals' => 2],
            'total_amount' => ['label' => 'Total', 'total_key' => 'total_amount', 'decimals' => 2],
            'net_total' => ['label' => 'Total', 'total_key' => 'total_amount', 'decimals' => 2],
            'remarks' => ['label' => 'Remarks', 'total' => ''],
        ];
    }

    private function salesSummaryVisibleColumns(Request $request): array
    {
        return $this->visibleReportColumns($request, $this->salesSummaryColumnDefinitions(), [
            'voucher_no', 'date', 'customer', 'qty_pcs', 'gross_weight', 'other_weight', 'net_weight',
            'purity', 'waste_percent', 'fine_weight', 'metal_rate', 'metal_amount', 'labour_rate', 'labour_amount', 'other_amount', 'net_total', 'remarks', 'created_by',
        ]);
    }

    private function salesSummaryDetailsVisibleColumns(Request $request): array
    {
        $definitions = $this->salesSummaryDetailsColumnDefinitions();
        $selected = $this->requestedColumns($request);
        if (!empty($selected)) {
            return $this->columnsByKeys($definitions, $selected);
        }

        $visibleKeys = ['label', 'huid', 'item'];

        foreach (['qty_pcs', 'gross_weight', 'other_weight', 'net_weight', 'purity', 'waste_percent', 'net_purity', 'fine_weight', 'metal_rate', 'metal_amount', 'labour_rate', 'labour_amount', 'other_amount', 'total_amount', 'remarks'] as $key) {
            if (empty($selected) || in_array($key, $selected, true) || ($key === 'total_amount' && in_array('net_total', $selected, true))) {
                $visibleKeys[] = $key;
            }
        }

        return $this->columnsByKeys($definitions, $visibleKeys);
    }

    private function salesSummaryDetailsPayload(int $companyId, Sale $sale): ?array
    {
        $company = Company::findOrFail($companyId);
        $allowedIds = \App\Services\ReportBranches::ids($company);
        if (!in_array((int) $sale->company_id, $allowedIds, true)) {
            return null;
        }
        $branch = Company::findOrFail($sale->company_id);

        $sale->load(['customer', 'creator', 'saleItems.itemset.item', 'saleItems.product']);

        $items = $sale->saleItems
            ->sortBy('id')
            ->values()
            ->map(function ($row) use ($sale) {
                $itemSet = $row->itemset;
                $item = $row->product ?? optional($itemSet)->item;
                $grossWeight = (float) ($row->gross_weight ?? 0);
                $otherWeight = (float) ($row->other_weight ?? 0);
                $netWeight = (float) ($row->net_weight ?? max(0, $grossWeight - $otherWeight));
                $fineWeight = (float) ($row->fine_weight ?? 0);
                $metalAmount = (float) ($row->metal_amount ?? 0);
                $labourAmount = (float) ($row->labour_amount ?? 0);
                $otherAmount = (float) ($row->other_amount ?? 0);
                $totalAmount = (float) ($row->total_amount ?? 0);

                return [
                    'sale_item_id' => (int) $row->id,
                    'itemset_id' => $row->itemset_id ? (int) $row->itemset_id : null,
                    'item_id' => $row->product_id ? (int) $row->product_id : null,
                    'voucher_no' => $sale->voucher_no,
                    'date' => optional($sale->sale_date)?->format('d-m-Y') ?? '-',
                    'customer' => optional($sale->customer)->name ?? '-',
                    'label' => optional($itemSet)->qr_code ?? optional($itemSet)->barcode ?? '-',
                    'huid' => optional($itemSet)->HUID ?? '-',
                    'item_name' => optional($item)->item_name ?? '-',
                    'qty_pcs' => (int) ($row->qty ?? 0),
                    'gross_weight' => $grossWeight,
                    'other_weight' => $otherWeight,
                    'net_weight' => $netWeight,
                    'purity' => (float) ($row->purity ?? 0),
                    'waste_percent' => (float) ($row->waste_percent ?? 0),
                    'net_purity' => (float) ($row->net_purity ?? 0),
                    'fine_weight' => $fineWeight,
                    'metal_rate' => (float) ($row->metal_rate ?? 0),
                    'metal_amount' => $metalAmount,
                    'labour_rate' => (float) ($row->labour_rate ?? 0),
                    'labour_amount' => $labourAmount,
                    'other_amount' => $otherAmount,
                    'total_amount' => $totalAmount,
                    'net_total' => $totalAmount,
                    'remarks' => $row->remarks ?? '',
                ];
            });

        return [
            'sale' => [
                'company_id' => (int) $branch->id,
                'branch_name' => $branch->name,
                'id' => (int) $sale->id,
                'voucher_no' => $sale->voucher_no,
                'sale_date' => optional($sale->sale_date)?->format('Y-m-d'),
                'sale_date_fmt' => optional($sale->sale_date)?->format('d-m-Y') ?? '-',
                'customer_id' => (int) ($sale->customer_id ?? 0),
                'customer_name' => optional($sale->customer)->name ?? '-',
                'remarks' => $sale->remarks ?? '-',
                'created_by' => optional($sale->creator)->name ?? '-',
            ],
            'summary' => [
                'qty_pcs' => (int) $items->sum('qty_pcs'),
                'gross_weight' => (float) $items->sum('gross_weight'),
                'other_weight' => (float) $items->sum('other_weight'),
                'net_weight' => (float) $items->sum('net_weight'),
                'fine_weight' => (float) $items->sum('fine_weight'),
                'metal_amount' => (float) $items->sum('metal_amount'),
                'labour_amount' => (float) $items->sum('labour_amount'),
                'other_amount' => (float) $items->sum('other_amount'),
                'total_amount' => (float) $items->sum('total_amount'),
            ],
            'data' => $items,
        ];
    }

    private function salesSummaryExportRow($row, array $visibleColumns): array
    {
        $values = [
            'voucher_no' => $row->voucher_no,
            'date' => optional($row->sale_date)?->format('d-m-Y'),
            'customer' => optional($row->customer)->name ?? '-',
            'qty_pcs' => (int) ($row->total_qty ?? 0),
            'gross_weight' => number_format((float) ($row->total_gross_weight ?? 0), 3, '.', ''),
            'other_weight' => number_format((float) ($row->total_other_weight ?? 0), 3, '.', ''),
            'net_weight' => number_format((float) ($row->total_net_weight ?? 0), 3, '.', ''),
            'purity' => number_format((float) ($row->total_purity ?? 0), 3, '.', ''),
            'waste_percent' => number_format((float) ($row->total_waste_percent ?? 0), 3, '.', ''),
            'fine_weight' => number_format((float) ($row->total_fine_weight ?? 0), 3, '.', ''),
            'metal_rate' => number_format((float) ($row->total_metal_rate ?? 0), 2, '.', ''),
            'metal_amount' => number_format((float) ($row->total_metal_amount ?? 0), 2, '.', ''),
            'labour_rate' => number_format((float) ($row->total_labour_rate ?? 0), 2, '.', ''),
            'labour_amount' => number_format((float) ($row->total_labour_amount ?? 0), 2, '.', ''),
            'other_amount' => number_format((float) ($row->total_other_amount ?? 0), 2, '.', ''),
            'net_total' => number_format((float) ($row->net_total ?? 0), 2, '.', ''),
            'remarks' => $row->remarks ?? '-',
            'created_by' => optional($row->creator)->name ?? '-',
        ];

        return array_map(fn($key) => $values[$key] ?? '', array_keys($visibleColumns));
    }

    private function salesSummaryDetailsExportRow(array $item, array $columns): array
    {
        $values = [
            'voucher_no' => $item['voucher_no'] ?? '-',
            'date' => $item['date'] ?? '-',
            'customer' => $item['customer'] ?? '-',
            'label' => $item['label'] ?? '-',
            'huid' => $item['huid'] ?? '-',
            'item' => $item['item_name'] ?? '-',
            'item_name' => $item['item_name'] ?? '-',
            'qty_pcs' => $item['qty_pcs'] ?? 0,
            'gross_weight' => number_format((float) ($item['gross_weight'] ?? 0), 3, '.', ''),
            'other_weight' => number_format((float) ($item['other_weight'] ?? 0), 3, '.', ''),
            'net_weight' => number_format((float) ($item['net_weight'] ?? 0), 3, '.', ''),
            'purity' => number_format((float) ($item['purity'] ?? 0), 3, '.', ''),
            'waste_percent' => number_format((float) ($item['waste_percent'] ?? 0), 3, '.', ''),
            'net_purity' => number_format((float) ($item['net_purity'] ?? 0), 3, '.', ''),
            'fine_weight' => number_format((float) ($item['fine_weight'] ?? 0), 3, '.', ''),
            'metal_rate' => number_format((float) ($item['metal_rate'] ?? 0), 2, '.', ''),
            'metal_amount' => number_format((float) ($item['metal_amount'] ?? 0), 2, '.', ''),
            'labour_rate' => number_format((float) ($item['labour_rate'] ?? 0), 2, '.', ''),
            'labour_amount' => number_format((float) ($item['labour_amount'] ?? 0), 2, '.', ''),
            'other_amount' => number_format((float) ($item['other_amount'] ?? 0), 2, '.', ''),
            'total_amount' => number_format((float) ($item['total_amount'] ?? 0), 2, '.', ''),
            'net_total' => number_format((float) ($item['net_total'] ?? $item['total_amount'] ?? 0), 2, '.', ''),
            'remarks' => $item['remarks'] ?? '-',
        ];

        return array_map(fn($key) => $values[$key] ?? '', array_keys($columns));
    }

    private function salesSummaryBaseQuery(Request $request, int $companyId)
    {
        $query = Sale::with(['customer', 'creator'])
            ->select('sales.*')
            ->withSum('saleItems as total_qty', 'qty')
            ->withSum('saleItems as total_gross_weight', 'gross_weight')
            ->withSum('saleItems as total_other_weight', 'other_weight')
            ->withSum('saleItems as total_net_weight', 'net_weight')
            ->selectSub(function ($q) {
                $q->from('sale_items')
                    ->selectRaw('COALESCE(SUM(net_weight * purity) / NULLIF(SUM(net_weight), 0), 0)')
                    ->whereColumn('sale_items.sale_id', 'sales.id');
            }, 'total_purity')
            ->selectSub(function ($q) {
                $q->from('sale_items')
                    ->selectRaw('COALESCE(SUM(net_weight * waste_percent) / NULLIF(SUM(net_weight), 0), 0)')
                    ->whereColumn('sale_items.sale_id', 'sales.id');
            }, 'total_waste_percent')
            ->withSum('saleItems as total_fine_weight', 'fine_weight')
            ->selectSub(function ($q) {
                $q->from('sale_items')
                    ->selectRaw('COALESCE(SUM(COALESCE(fine_weight, 0) * COALESCE(metal_rate, 0)) / NULLIF(SUM(COALESCE(fine_weight, 0)), 0), 0)')
                    ->whereColumn('sale_items.sale_id', 'sales.id');
            }, 'total_metal_rate')
            ->withSum('saleItems as total_metal_amount', 'metal_amount')
            ->selectSub(function ($q) {
                $q->from('sale_items')
                    ->selectRaw('COALESCE(SUM(COALESCE(net_weight, 0) * COALESCE(labour_rate, 0)) / NULLIF(SUM(COALESCE(net_weight, 0)), 0), 0)')
                    ->whereColumn('sale_items.sale_id', 'sales.id');
            }, 'total_labour_rate')
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
                COALESCE(SUM(sale_items.other_weight), 0) as other_weight,
                COALESCE(SUM(sale_items.net_weight), 0) as net_weight,
                COALESCE(SUM(sale_items.net_weight * sale_items.purity) / NULLIF(SUM(sale_items.net_weight), 0), 0) as purity,
                COALESCE(SUM(sale_items.net_weight * sale_items.waste_percent) / NULLIF(SUM(sale_items.net_weight), 0), 0) as waste_percent,
                COALESCE(SUM(sale_items.fine_weight), 0) as fine_weight,
                COALESCE(SUM(COALESCE(sale_items.fine_weight, 0) * COALESCE(sale_items.metal_rate, 0)) / NULLIF(SUM(COALESCE(sale_items.fine_weight, 0)), 0), 0) as metal_rate,
                COALESCE(SUM(sale_items.metal_amount), 0) as metal_amount,
                COALESCE(SUM(COALESCE(sale_items.net_weight, 0) * COALESCE(sale_items.labour_rate, 0)) / NULLIF(SUM(COALESCE(sale_items.net_weight, 0)), 0), 0) as labour_rate,
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
            'other_weight' => (float) ($weightAndAmountTotals->other_weight ?? 0),
            'net_weight' => (float) ($weightAndAmountTotals->net_weight ?? 0),
            'purity' => (float) ($weightAndAmountTotals->purity ?? 0),
            'waste_percent' => (float) ($weightAndAmountTotals->waste_percent ?? 0),
            'fine_weight' => (float) ($weightAndAmountTotals->fine_weight ?? 0),
            'metal_rate' => (float) ($weightAndAmountTotals->metal_rate ?? 0),
            'metal_amount' => (float) ($weightAndAmountTotals->metal_amount ?? 0),
            'labour_rate' => (float) ($weightAndAmountTotals->labour_rate ?? 0),
            'labour_amount' => (float) ($weightAndAmountTotals->labour_amount ?? 0),
            'other_amount' => (float) ($weightAndAmountTotals->other_amount ?? 0),
            'net_total' => (float) ($netTotal ?? 0),
        ];
    }

    private function purchaseReceiverSummaryBaseQuery(Request $request, int $companyId)
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
        $request->validate(['stock_type'=>'nullable|in:raw_material,finished_item,customer_received,scrap,repair']);
        $labelStock = ItemSet::query()
            ->join('items', 'items.id', '=', 'item_sets.item_id')
            ->leftJoin('customers as supplier_customers', function ($join) use ($companyId) {
                $join->on('supplier_customers.name', '=', 'item_sets.supplier_person')
                    ->where('supplier_customers.company_id', $companyId);
            })
            ->where('item_sets.company_id', $companyId)
            ->where('item_sets.is_final', 1)
            ->where('item_sets.is_sold', 0)
            ->when($request->filled('item_id'), function ($q) use ($request) {
                $q->where('item_sets.item_id', (int) $request->item_id);
            })
            ->when($request->filled('customer_id'), function ($q) use ($request) {
                $q->where('supplier_customers.id', (int) $request->customer_id);
            })
            ->select([
                'item_sets.item_id',
                'items.item_name',
                DB::raw("'finished_item' as stock_type"),
                DB::raw("'Finished Item' as stock_type_name"),
                DB::raw('supplier_customers.id as customer_id'),
                DB::raw("COALESCE(NULLIF(item_sets.supplier_person, ''), '-') as customer_name"),
                DB::raw('COUNT(item_sets.id) as qty_pcs'),
                DB::raw('SUM(COALESCE(item_sets.gross_weight,0)) as gross_weight'),
                DB::raw('SUM(COALESCE(item_sets.other,0)) as less_weight'),
                DB::raw('SUM(COALESCE(item_sets.net_weight,0)) as net_weight'),
                DB::raw('SUM(COALESCE(item_sets.net_weight,0)) as fine_weight'),
                DB::raw('SUM(COALESCE(item_sets.sale_labour_amount,0)) as labour_amount'),
                DB::raw('SUM(COALESCE(item_sets.sale_other,0)) as other_amount'),
                DB::raw('MAX(item_sets.created_at) as created_at'),
            ])
            ->groupBy('item_sets.item_id', 'items.item_name', 'supplier_customers.id', 'item_sets.supplier_person');

        $customerReceivedStock = DB::table('customer_advance_voucher_items as cavi')
            ->leftJoinSub(app(\App\Services\JobworkRawStock::class)->consumption((int) $companyId), 'raw_used', 'raw_used.voucher_item_id', '=', 'cavi.id')
            ->join('customer_advance_vouchers as cav', 'cav.id', '=', 'cavi.voucher_id')
            ->join('customers as customers', 'customers.id', '=', 'cav.customer_id')
            ->leftJoin('items', 'items.id', '=', 'cavi.product_id')
            ->where('cav.company_id', $companyId)
            ->when($request->filled('item_id'), function ($q) use ($request) {
                $q->where('cavi.product_id', (int) $request->item_id);
            })
            ->when($request->filled('customer_id'), function ($q) use ($request) {
                $q->where('cav.customer_id', (int) $request->customer_id);
            })
            ->select([
                DB::raw('COALESCE(cavi.product_id, 0) as item_id'),
                DB::raw("COALESCE(items.item_name, cavi.item_name, 'Unknown Item') as item_name"),
                DB::raw("'customer_received' as stock_type"),
                DB::raw("'Customer Received' as stock_type_name"),
                'cav.customer_id',
                DB::raw('customers.name as customer_name'),
                DB::raw('SUM(CASE WHEN COALESCE(raw_used.used_net,0) > 0 AND COALESCE(cavi.net_weight,0) - raw_used.used_net < 0.0005 THEN 0 ELSE 1 END) as qty_pcs'),
                DB::raw('SUM(' . \App\Services\JobworkRawStock::reportWeight('gross_weight') . ') as gross_weight'),
                DB::raw('SUM(' . \App\Services\JobworkRawStock::reportWeight('other_weight') . ') as less_weight'),
                DB::raw('SUM(' . \App\Services\JobworkRawStock::reportWeight('net_weight') . ') as net_weight'),
                DB::raw('SUM(' . \App\Services\JobworkRawStock::reportWeight('fine_weight') . ') as fine_weight'),
                DB::raw('SUM(COALESCE(cavi.labour_amount,0)) as labour_amount'),
                DB::raw('SUM(COALESCE(cavi.other_amount,0)) as other_amount'),
                DB::raw('MAX(cavi.created_at) as created_at'),
            ])
            ->groupBy('cavi.product_id', 'items.item_name', 'cavi.item_name', 'cav.customer_id', 'customers.name');

        $castingSortingStock = DB::table('casting_sorting_items as csi')
            ->join('items', 'items.id', '=', 'csi.item_id')
            ->where('csi.company_id', $companyId)
            ->when($request->filled('item_id'), function ($q) use ($request) {
                $q->where('csi.item_id', (int) $request->item_id);
            })
            ->when($request->filled('customer_id'), function ($q) {
                $q->whereRaw('1 = 0');
            })
            ->select([
                'csi.item_id',
                'items.item_name',
                DB::raw("COALESCE(csi.stock_type, 'raw_material') as stock_type"),
                DB::raw("CASE COALESCE(csi.stock_type, 'raw_material')
                    WHEN 'finished_item' THEN 'Finished Item'
                    WHEN 'scrap' THEN 'Scrap'
                    WHEN 'repair' THEN 'Repair'
                    ELSE 'Raw Material'
                END as stock_type_name"),
                DB::raw('NULL as customer_id'),
                DB::raw("'-' as customer_name"),
                DB::raw('SUM(COALESCE(csi.quantity,0)) as qty_pcs'),
                DB::raw('SUM(COALESCE(csi.weight,0)) as gross_weight'),
                DB::raw('0 as less_weight'),
                DB::raw('SUM(COALESCE(csi.weight,0)) as net_weight'),
                DB::raw('SUM(COALESCE(csi.weight,0)) as fine_weight'),
                DB::raw('0 as labour_amount'),
                DB::raw('0 as other_amount'),
                DB::raw('MAX(csi.created_at) as created_at'),
            ])
            ->groupBy('csi.item_id', 'items.item_name', 'csi.stock_type');

        $search = trim((string) ($request->input('search') ?: $request->input('search_text') ?: $request->input('party_name', '')));

        $grouped = DB::query()
            ->fromSub($labelStock->unionAll($customerReceivedStock)->unionAll($castingSortingStock)->unionAll(
                app(\App\Services\JobworkRawStock::class)->debtReport((int) $companyId, true)
                    ->when($request->filled('item_id'), fn($q) => $q->where('debt.item_id', (int) $request->item_id))
                    ->when($request->filled('customer_id'), fn($q) => $q->whereRaw('1 = 0'))
            ), 'stock_rows')
            ->when($request->filled('stock_type'), fn ($q) => $q->where('stock_rows.stock_type', $request->input('stock_type')))
            ->select([
                'stock_rows.item_id',
                'stock_rows.item_name',
                'stock_rows.stock_type',
                'stock_rows.stock_type_name',
                'stock_rows.customer_id',
                'stock_rows.customer_name',
                DB::raw('SUM(stock_rows.qty_pcs) as qty_pcs'),
                DB::raw('SUM(stock_rows.gross_weight) as gross_weight'),
                DB::raw('SUM(stock_rows.less_weight) as less_weight'),
                DB::raw('SUM(stock_rows.less_weight) as other_weight'),
                DB::raw('SUM(stock_rows.net_weight) as net_weight'),
                DB::raw('SUM(stock_rows.fine_weight) as fine_weight'),
                DB::raw('SUM(stock_rows.labour_amount) as labour_amount'),
                DB::raw('SUM(stock_rows.other_amount) as other_amount'),
                DB::raw('MAX(stock_rows.created_at) as created_at'),
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('stock_rows.customer_name', 'like', '%' . $search . '%')
                        ->orWhere('stock_rows.item_name', 'like', '%' . $search . '%')
                        ->orWhere('stock_rows.stock_type_name', 'like', '%' . $search . '%');
                });
            })
            ->groupBy('stock_rows.item_id', 'stock_rows.item_name', 'stock_rows.stock_type', 'stock_rows.stock_type_name', 'stock_rows.customer_id', 'stock_rows.customer_name');
        return DB::query()->fromSub($grouped, 'stock_group')->select('stock_group.*')
            ->selectRaw("CASE WHEN stock_type = 'finished_item' THEN (SELECT COUNT(*) FROM item_sets img WHERE img.company_id = ? AND img.item_id = stock_group.item_id AND img.is_final = 1 AND img.is_sold = 0 AND COALESCE(NULLIF(img.supplier_person, ''), '-') = stock_group.customer_name AND NULLIF(TRIM(img.image_path), '') IS NOT NULL) ELSE 0 END as with_image", [$companyId]);
    }

    private function workerLossBaseQuery(Request $request, int $companyId)
    {
        return app(\App\Http\Controllers\Company\ReportController::class)
            ->workerLossBaseQuery(Company::findOrFail($companyId), $request);
    }
    private function workerLossTotals(Request $request, int $companyId): array
    {
        $rows = $this->workerLossBaseQuery($request, $companyId)->get();

        return [
            'row_count' => $rows->count(),
            'source_wt' => (float) $rows->sum(fn($row) => (float) ($row->source_wt ?? 0)),
            'receive_wt' => (float) $rows->sum(fn($row) => (float) ($row->receive_wt ?? 0)),
            'bhuko' => (float) $rows->sum(fn($row) => (float) ($row->bhuko ?? 0)),
            'loss' => (float) $rows->sum(fn($row) => (float) ($row->loss ?? 0)),
        ];
    }

    private function workerLossSummary(Request $request, int $companyId): array
    {
        $rows = $this->workerLossBaseQuery($request, $companyId)->get();
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

    private function truthyApiValue($value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
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
                'items as pending_gross_weight' => function ($q) {
                    $q->where('status', 'pending');
                }
            ], 'gross_weight')
            ->withSum([
                'items as pending_other_weight' => function ($q) {
                    $q->where('status', 'pending');
                }
            ], 'other_weight')
            ->withSum([
                'items as pending_net_weight' => function ($q) {
                    $q->where('status', 'pending');
                }
            ], 'net_weight')
            ->withAvg([
                'items as pending_purity' => function ($q) {
                    $q->where('status', 'pending');
                }
            ], 'purity')
            ->withAvg([
                'items as pending_waste_percent' => function ($q) {
                    $q->where('status', 'pending');
                }
            ], 'waste_percent')
            ->withAvg([
                'items as pending_metal_rate' => function ($q) {
                    $q->where('status', 'pending');
                }
            ], 'metal_rate')
            ->withAvg([
                'items as pending_labour_rate' => function ($q) {
                    $q->where('status', 'pending');
                }
            ], 'labour_rate')
            ->withSum([
                'items as pending_other_amount' => function ($q) {
                    $q->where('status', 'pending');
                }
            ], 'other_amount')
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

    private function approvalOutstandingTotals(Collection $rows): array
    {
        $netWeight = (float) $rows->sum(fn($r) => (float) ($r->pending_net_weight ?? 0));
        $weightedAverage = function (string $field) use ($rows, $netWeight): float {
            if ($netWeight <= 0) {
                return 0.0;
            }

            return (float) $rows->sum(fn($r) => (float) ($r->pending_net_weight ?? 0) * (float) ($r->{$field} ?? 0)) / $netWeight;
        };

        return [
            'voucher_count' => (int) $rows->count(),
            'pending_pcs' => (int) $rows->sum(fn($r) => (int) ($r->pending_items_count ?? 0)),
            'pending_gross_weight' => (float) $rows->sum(fn($r) => (float) ($r->pending_gross_weight ?? 0)),
            'pending_other_weight' => (float) $rows->sum(fn($r) => (float) ($r->pending_other_weight ?? 0)),
            'pending_net_weight' => $netWeight,
            'pending_purity' => $weightedAverage('pending_purity'),
            'pending_waste_percent' => $weightedAverage('pending_waste_percent'),
            'pending_metal_rate' => $weightedAverage('pending_metal_rate'),
            'pending_labour_rate' => $weightedAverage('pending_labour_rate'),
            'pending_other_amount' => (float) $rows->sum(fn($r) => (float) ($r->pending_other_amount ?? 0)),
            'pending_amount' => (float) $rows->sum(fn($r) => (float) ($r->pending_total_amount ?? 0)),
        ];
    }

    private function approvalOutstandingVoucherTotals(int $companyId, array $approvalIds): array
    {
        $approvalIds = array_values(array_filter(array_unique(array_map('intval', $approvalIds))));
        if (empty($approvalIds)) {
            return [];
        }

        $hasFineWeight = Schema::hasColumn('approval_items', 'fine_weight');
        $hasTotalFineWeight = Schema::hasColumn('approval_items', 'total_fine_weight');
        $hasMetalAmount = Schema::hasColumn('approval_items', 'metal_amount');
        $hasLabourAmount = Schema::hasColumn('approval_items', 'labour_amount');
        $hasOtherAmount = Schema::hasColumn('approval_items', 'other_amount');
        $hasTotalAmount = Schema::hasColumn('approval_items', 'total_amount');

        $fineExpr = '0';
        if ($hasTotalFineWeight && $hasFineWeight) {
            $fineExpr = 'COALESCE(ai.total_fine_weight, ai.fine_weight, 0)';
        } elseif ($hasTotalFineWeight) {
            $fineExpr = 'COALESCE(ai.total_fine_weight, 0)';
        } elseif ($hasFineWeight) {
            $fineExpr = 'COALESCE(ai.fine_weight, 0)';
        }

        $metalExpr = $hasMetalAmount ? 'COALESCE(ai.metal_amount, 0)' : '0';
        $labourExpr = $hasLabourAmount ? 'COALESCE(ai.labour_amount, 0)' : '0';
        $otherExpr = $hasOtherAmount ? 'COALESCE(ai.other_amount, 0)' : '0';
        $totalExpr = $hasTotalAmount ? 'COALESCE(ai.total_amount, 0)' : '0';

        return DB::table('approval_items as ai')
            ->join('approval_headers as ah', 'ah.id', '=', 'ai.approval_id')
            ->where('ah.company_id', $companyId)
            ->whereIn('ai.approval_id', $approvalIds)
            ->where('ai.status', 'pending')
            ->groupBy('ai.approval_id')
            ->selectRaw("
                ai.approval_id,
                COUNT(ai.id) as qty_pcs,
                COALESCE(SUM(COALESCE(ai.gross_weight, 0)), 0) as gross_weight,
                COALESCE(SUM(COALESCE(ai.other_weight, 0)), 0) as other_weight,
                COALESCE(SUM(COALESCE(ai.net_weight, 0)), 0) as net_weight,
                COALESCE(SUM(COALESCE(ai.net_weight, 0) * COALESCE(ai.purity, 0)) / NULLIF(SUM(COALESCE(ai.net_weight, 0)), 0), 0) as purity,
                COALESCE(SUM(COALESCE(ai.net_weight, 0) * COALESCE(ai.waste_percent, 0)) / NULLIF(SUM(COALESCE(ai.net_weight, 0)), 0), 0) as waste_percent,
                COALESCE(SUM(COALESCE(ai.net_weight, 0) * COALESCE(ai.metal_rate, 0)) / NULLIF(SUM(COALESCE(ai.net_weight, 0)), 0), 0) as metal_rate,
                COALESCE(SUM(COALESCE(ai.net_weight, 0) * COALESCE(ai.labour_rate, 0)) / NULLIF(SUM(COALESCE(ai.net_weight, 0)), 0), 0) as labour_rate,
                COALESCE(SUM({$fineExpr}), 0) as fine_weight,
                COALESCE(SUM({$metalExpr}), 0) as metal_amount,
                COALESCE(SUM({$labourExpr}), 0) as labour_amount,
                COALESCE(SUM({$otherExpr}), 0) as other_amount,
                COALESCE(SUM({$totalExpr}), 0) as net_total
            ")
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    (int) $row->approval_id => [
                        'qty_pcs' => (int) ($row->qty_pcs ?? 0),
                        'gross_weight' => (float) ($row->gross_weight ?? 0),
                        'other_weight' => (float) ($row->other_weight ?? 0),
                        'net_weight' => (float) ($row->net_weight ?? 0),
                        'fine_weight' => (float) ($row->fine_weight ?? 0),
                        'metal_amount' => (float) ($row->metal_amount ?? 0),
                        'labour_amount' => (float) ($row->labour_amount ?? 0),
                        'other_amount' => (float) ($row->other_amount ?? 0),
                        'net_total' => (float) ($row->net_total ?? 0),
                    ],
                ];
            })
            ->all();
    }

    private function approvalOutstandingTotalsDetailed(Request $request, int $companyId): array
    {
        $hasFineWeight = Schema::hasColumn('approval_items', 'fine_weight');
        $hasTotalFineWeight = Schema::hasColumn('approval_items', 'total_fine_weight');
        $hasMetalAmount = Schema::hasColumn('approval_items', 'metal_amount');
        $hasLabourAmount = Schema::hasColumn('approval_items', 'labour_amount');
        $hasOtherAmount = Schema::hasColumn('approval_items', 'other_amount');
        $hasTotalAmount = Schema::hasColumn('approval_items', 'total_amount');

        $fineExpr = '0';
        if ($hasTotalFineWeight && $hasFineWeight) {
            $fineExpr = 'COALESCE(ai.total_fine_weight, ai.fine_weight, 0)';
        } elseif ($hasTotalFineWeight) {
            $fineExpr = 'COALESCE(ai.total_fine_weight, 0)';
        } elseif ($hasFineWeight) {
            $fineExpr = 'COALESCE(ai.fine_weight, 0)';
        }

        $metalExpr = $hasMetalAmount ? 'COALESCE(ai.metal_amount, 0)' : '0';
        $labourExpr = $hasLabourAmount ? 'COALESCE(ai.labour_amount, 0)' : '0';
        $otherExpr = $hasOtherAmount ? 'COALESCE(ai.other_amount, 0)' : '0';
        $totalExpr = $hasTotalAmount ? 'COALESCE(ai.total_amount, 0)' : '0';

        $totals = DB::table('approval_items as ai')
            ->join('approval_headers as ah', 'ah.id', '=', 'ai.approval_id')
            ->where('ah.company_id', $companyId)
            ->whereIn('ah.status', ['open', 'partial'])
            ->where('ai.status', 'pending')
            ->when($request->filled('customer_id'), function ($q) use ($request) {
                $q->where('ah.customer_id', (int) $request->customer_id);
            })
            ->when($request->filled('from_date') && $request->filled('to_date'), function ($q) use ($request) {
                $q->whereBetween('ah.approval_date', [$request->from_date, $request->to_date]);
            })
            ->when($request->filled('from_date') && !$request->filled('to_date'), function ($q) use ($request) {
                $q->whereDate('ah.approval_date', '>=', $request->from_date);
            })
            ->when(!$request->filled('from_date') && $request->filled('to_date'), function ($q) use ($request) {
                $q->whereDate('ah.approval_date', '<=', $request->to_date);
            })
            ->selectRaw("
                COUNT(ai.id) as qty_pcs,
                COALESCE(SUM(COALESCE(ai.gross_weight, 0)), 0) as gross_weight,
                COALESCE(SUM(COALESCE(ai.other_weight, 0)), 0) as other_weight,
                COALESCE(SUM(COALESCE(ai.net_weight, 0)), 0) as net_weight,
                COALESCE(SUM({$fineExpr}), 0) as fine_weight,
                COALESCE(SUM({$metalExpr}), 0) as metal_amount,
                COALESCE(SUM({$labourExpr}), 0) as labour_amount,
                COALESCE(SUM({$otherExpr}), 0) as other_amount,
                COALESCE(SUM({$totalExpr}), 0) as net_total
            ")
            ->first();

        return [
            'qty_pcs' => (int) ($totals->qty_pcs ?? 0),
            'gross_weight' => (float) ($totals->gross_weight ?? 0),
            'other_weight' => (float) ($totals->other_weight ?? 0),
            'net_weight' => (float) ($totals->net_weight ?? 0),
            'purity' => (float) ($totals->purity ?? 0),
            'waste_percent' => (float) ($totals->waste_percent ?? 0),
            'metal_rate' => (float) ($totals->metal_rate ?? 0),
            'labour_rate' => (float) ($totals->labour_rate ?? 0),
            'fine_weight' => (float) ($totals->fine_weight ?? 0),
            'metal_amount' => (float) ($totals->metal_amount ?? 0),
            'labour_amount' => (float) ($totals->labour_amount ?? 0),
            'other_amount' => (float) ($totals->other_amount ?? 0),
            'net_total' => (float) ($totals->net_total ?? 0),
            'pending_pcs' => (int) ($totals->qty_pcs ?? 0),
            'pending_gross_weight' => (float) ($totals->gross_weight ?? 0),
            'pending_other_weight' => (float) ($totals->other_weight ?? 0),
            'pending_net_weight' => (float) ($totals->net_weight ?? 0),
            'pending_purity' => (float) ($totals->purity ?? 0),
            'pending_waste_percent' => (float) ($totals->waste_percent ?? 0),
            'pending_metal_rate' => (float) ($totals->metal_rate ?? 0),
            'pending_labour_rate' => (float) ($totals->labour_rate ?? 0),
            'pending_other_amount' => (float) ($totals->other_amount ?? 0),
            'pending_amount' => (float) ($totals->net_total ?? 0),
        ];
    }

    private function outstandingAmountBaseQuery(Request $request, int $companyId)
    {
        $query = Sale::with(['customer'])
            ->withSum('saleItems as sum_gross_weight', 'gross_weight')
            ->withSum('saleItems as sum_net_weight', 'net_weight')
            ->withSum('saleItems as sum_fine_weight', 'fine_weight')
            ->withSum('saleItems as sum_metal_amount', 'metal_amount')
            ->withSum('saleItems as sum_labour_amount', 'labour_amount')
            ->where('company_id', $companyId);

        $hasUseToggles = $this->hasAnyUseToggle($request);
        $useDate = $this->isToggleEnabled($request, ['use_date']);
        $useCustomer = $this->isToggleEnabled($request, ['use_customer', 'use_party']);
        $useCity = $this->isToggleEnabled($request, ['use_city']);
        $useMode = $this->isToggleEnabled($request, ['use_payment_mode', 'use_mode']);
        $useWeight = $this->isToggleEnabled($request, ['use_weight']);
        $useAmount = $this->isToggleEnabled($request, ['use_amount']);

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
                $query->having('sum_net_weight', '>=', (float) $request->weight_from);
            }
            if ($request->filled('weight_to')) {
                $query->having('sum_net_weight', '<=', (float) $request->weight_to);
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

    private function isToggleEnabled(Request $request, array $keys): bool
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

    private function hasAnyUseToggle(Request $request): bool
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
        $metalDebit = (float) $rows->sum(fn($r) => $this->outstandingMetalDebit($r));
        $pendingAmount = (float) $rows->sum(function ($r) {
            $received = (float) ($r->received_amount ?? 0);
            $out = (float) ($r->paid_amount ?? 0);
            return max(0, (float) ($r->net_total ?? 0) - $r->appliedReceivedAmount());
        });

        return [
            'voucher_count' => (int) $rows->count(),
            'gross_weight' => (float) $rows->sum(fn($r) => (float) ($r->sum_gross_weight ?? 0)),
            'net_weight' => (float) $rows->sum(fn($r) => (float) ($r->sum_net_weight ?? 0)),
            'fine_weight' => (float) $rows->sum(fn($r) => (float) ($r->sum_fine_weight ?? 0)),
            'metal_amount' => (float) $rows->sum(fn($r) => (float) ($r->sum_metal_amount ?? 0)),
            'labour_amount' => (float) $rows->sum(fn($r) => (float) ($r->sum_labour_amount ?? 0)),
            'metal_debit' => $metalDebit,
            'total_amount' => $totalAmount,
            'amount_in' => $amountIn,
            'amount_out' => $amountOut,
            'pending_amount' => $pendingAmount,
        ];
    }

    private function outstandingMetalDebit($row): float
    {
        $fineWeight = (float) ($row->sum_fine_weight ?? 0);
        $metalAmount = (float) ($row->sum_metal_amount ?? 0);
        $useMetalBalance = (bool) ($row->use_silver_balance ?? false);

        return (!$useMetalBalance && $metalAmount <= 0.000001 && $fineWeight > 0.000001) ? $fineWeight : 0.0;
    }
}
