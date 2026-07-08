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
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('Y-m-d H:i:s') : null,
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
                    'pending_net_weight' => (float) ($row->pending_net_weight ?? 0),
                    'pending_total_amount' => (float) ($row->pending_total_amount ?? 0),
                    'qty_pcs' => (int) ($rowTotals['qty_pcs'] ?? 0),
                    'gross_weight' => (float) ($rowTotals['gross_weight'] ?? 0),
                    'net_weight' => (float) ($rowTotals['net_weight'] ?? 0),
                    'fine_weight' => (float) ($rowTotals['fine_weight'] ?? 0),
                    'metal_amount' => (float) ($rowTotals['metal_amount'] ?? 0),
                    'labour_amount' => (float) ($rowTotals['labour_amount'] ?? 0),
                    'other_amount' => (float) ($rowTotals['other_amount'] ?? 0),
                    'net_total' => (float) ($rowTotals['net_total'] ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Approval outstanding fetched successfully.',
            'count' => $rows->count(),
            'totals' => $totals,
            'data' => $rows,
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

        $approval->load(['customer', 'creator']);

        $items = $approval->items()
            ->with(['itemSet.item', 'legacyItemSet.item', 'item'])
            ->where('status', 'pending')
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                $itemSet = $row->itemSet ?? $row->legacyItemSet;
                $item = optional($itemSet)->item ?? $row->item;
                $grossWeight = (float) ($row->gross_weight ?? optional($itemSet)->gross_weight ?? 0);
                $otherWeight = (float) ($row->other_weight ?? optional($itemSet)->other ?? 0);
                $netWeight = (float) ($row->net_weight ?? optional($itemSet)->net_weight ?? max(0, $grossWeight - $otherWeight));

                return [
                    'approval_item_id' => (int) $row->id,
                    'itemset_id' => optional($itemSet)->id,
                    'item_id' => $row->item_id ?? optional($itemSet)->item_id,
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

        return response()->json([
            'success' => true,
            'message' => 'Approval outstanding details fetched successfully.',
            'approval' => [
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
                'pending_pcs' => $items->count(),
                'pending_net_weight' => (float) $items->sum('net_weight'),
                'pending_amount' => (float) $items->sum('total_amount'),
            ],
            'data' => $items,
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
        $summary = $this->approvalOutstandingTotals($rows);

        return Pdf::loadView('company.reports.pdf.approval_outstanding', compact('company', 'rows', 'summary'))
            ->setPaper('a4', 'portrait')
            ->download('approval_outstanding_report.pdf');
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
            $pending = max(0, $totalAmount - ($received - $amountOut));

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

    public function outstandingAmountPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::select('id', 'name')->find($companyId);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }

        $rows = $this->outstandingAmountBaseQuery($request, $companyId)->latest('id')->get();
        $summary = $this->outstandingAmountTotals($rows);
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

        return Pdf::loadView('company.reports.pdf.outstanding_amount', compact('company', 'rows', 'summary', 'visible'))
            ->setPaper('a4', 'portrait')
            ->download('outstanding_amount_report.pdf');
    }

    public function outstandingAmountLedgerPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::select('id', 'name')->find($companyId);
        if (!$company) {
            $company = (object) ['name' => 'Company', 'company_name' => 'Company'];
        }

        $rows = $this->outstandingAmountBaseQuery($request, $companyId)
            ->latest('sale_date')
            ->latest('id')
            ->get();
        $summary = $this->outstandingAmountTotals($rows);

        return Pdf::loadView('company.reports.pdf.outstanding_amount_ledger', compact('company', 'rows', 'summary'))
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
                DB::raw('MAX(item_sets.created_at) as created_at'),
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

    private function approvalOutstandingTotals(Collection $rows): array
    {
        return [
            'voucher_count' => (int) $rows->count(),
            'pending_pcs' => (int) $rows->sum(fn($r) => (int) ($r->pending_items_count ?? 0)),
            'pending_net_weight' => (float) $rows->sum(fn($r) => (float) ($r->pending_net_weight ?? 0)),
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
                COALESCE(SUM(COALESCE(ai.net_weight, 0)), 0) as net_weight,
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
            'net_weight' => (float) ($totals->net_weight ?? 0),
            'fine_weight' => (float) ($totals->fine_weight ?? 0),
            'metal_amount' => (float) ($totals->metal_amount ?? 0),
            'labour_amount' => (float) ($totals->labour_amount ?? 0),
            'other_amount' => (float) ($totals->other_amount ?? 0),
            'net_total' => (float) ($totals->net_total ?? 0),
        ];
    }

    private function outstandingAmountBaseQuery(Request $request, int $companyId)
    {
        $query = Sale::with(['customer'])
            ->withSum('saleItems as sum_gross_weight', 'gross_weight')
            ->withSum('saleItems as sum_net_weight', 'net_weight')
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
}
