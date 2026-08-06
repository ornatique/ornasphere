<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CastingHeatingItem;
use App\Models\CastingMetalIssueItem;
use App\Models\CastingReleaseItem;
use App\Models\CastingSortingItem;
use App\Models\Company;
use App\Models\TreeCuttingIssueItem;
use App\Models\TreeCuttingOfficeItem;
use App\Models\TreeCuttingReceiveItem;
use App\Models\VacuumVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class VoucherHistoryApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $selectedVoucherId = $request->integer('voucher_id') ?: null;

        $vouchers = $this->voucherOptionsQuery($request, $companyId)
            ->limit($this->limit($request))
            ->get()
            ->map(fn($voucher) => $this->voucherOption($voucher))
            ->values();

        $voucher = null;
        $history = null;

        if ($selectedVoucherId) {
            $selectedVoucher = $this->findVoucher($companyId, $selectedVoucherId);

            if ($selectedVoucher) {
                $voucher = $this->voucherPayload($selectedVoucher);
                $history = $this->historyForVoucher($companyId, $selectedVoucher);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'vouchers' => $vouchers,
                'selected_voucher_id' => $selectedVoucherId,
                'voucher' => $voucher,
                'history' => $history,
            ],
        ]);
    }

    public function vouchers(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $vouchers = $this->voucherOptionsQuery($request, $companyId)
            ->limit($this->limit($request))
            ->get()
            ->map(fn($voucher) => $this->voucherOption($voucher))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $vouchers,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $companyId = (int) $request->user()->company_id;
        $voucher = $this->findVoucher($companyId, $id);

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'voucher' => $this->voucherPayload($voucher),
                'history' => $this->historyForVoucher($companyId, $voucher),
            ],
        ]);
    }

    public function pdf(Request $request, int $id)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::findOrFail($companyId);
        $voucher = $this->findVoucher($companyId, $id);

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found.',
            ], 404);
        }

        $history = $this->historyForPdf($companyId, $voucher);

        return Pdf::loadView('company.voucher_history.pdf.show', compact('company', 'voucher', 'history'))
            ->setPaper('a4', 'landscape')
            ->download('voucher_process_history_' . $voucher->voucher_no . '.pdf');
    }

    public function excel(Request $request, int $id)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::findOrFail($companyId);
        $voucher = $this->findVoucher($companyId, $id);

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found.',
            ], 404);
        }

        $history = $this->historyForPdf($companyId, $voucher);
        $fileName = 'voucher_process_history_' . $voucher->voucher_no . '.csv';
        $printedBy = $request->user()->name ?? '-';

        return response()->streamDownload(function () use ($company, $voucher, $history, $printedBy) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Voucher Process History']);
            fputcsv($handle, ['Company', $company->name]);
            fputcsv($handle, []);
            fputcsv($handle, ['Voucher No', $voucher->voucher_no, 'Voucher Date', optional($voucher->voucher_date)->format('d-m-Y'), 'Process', $voucher->process?->name ?? '-', 'Worker', $voucher->jobWorker?->name ?? '-']);
            fputcsv($handle, ['Gross Wt', $history['summary']['gross_wt'], 'Buch Wt', $history['summary']['buch_wt'], 'Net Wt', $history['summary']['net_wt'], 'Silver Wt', $history['summary']['silver_wt']]);
            fputcsv($handle, ['Total Pcs', $history['summary']['total_pcs'], 'Created At', optional($voucher->created_at)->format('d-m-Y h:i A'), 'Printed At', now()->format('d-m-Y h:i A'), 'Printed By', $printedBy]);
            fputcsv($handle, []);

            fputcsv($handle, ['1. Casting Heating']);
            fputcsv($handle, ['Sr No', 'Buch No', 'In Bhati', 'Checked At']);
            foreach ($history['casting_heating']['rows'] as $index => $row) {
                fputcsv($handle, [$index + 1, $row['buch_no'], $row['in_bhati'], $row['checked_at']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['2. Casting Metal Issue']);
            fputcsv($handle, ['Sr No', 'Buch No', 'Silver Wt', 'Issue Silver Wt', 'Issued At']);
            foreach ($history['casting_metal_issue']['rows'] as $index => $row) {
                fputcsv($handle, [$index + 1, $row['buch_no'], $row['silver_wt'], $row['issue_silver_wt'], $row['issued_at']]);
            }
            fputcsv($handle, ['Total', '', $history['casting_metal_issue']['totals']['silver_wt'], $history['casting_metal_issue']['totals']['issue_silver_wt'], '']);
            fputcsv($handle, []);

            fputcsv($handle, ['3. Casting Receive']);
            fputcsv($handle, ['Sr No', 'Buch No', 'Release Tree Wt', 'Tree Bhuko', 'Loss', 'Received At']);
            foreach ($history['casting_receive']['rows'] as $index => $row) {
                fputcsv($handle, [$index + 1, $row['buch_no'], $row['release_tree_wt'], $row['release_tree_bhuko'], $row['loss'], $row['received_at']]);
            }
            fputcsv($handle, ['Total', '', $history['casting_receive']['totals']['release_tree_wt'], $history['casting_receive']['totals']['release_tree_bhuko'], $history['casting_receive']['totals']['loss'], '']);
            fputcsv($handle, []);

            fputcsv($handle, ['4. Tree Cutting Issue Office']);
            fputcsv($handle, ['Sr No', 'Buch No', 'Tree Wt', 'Tree Bhuko', 'Remaining Tree Wt', 'Office At']);
            foreach ($history['tree_cutting_office']['rows'] as $index => $row) {
                fputcsv($handle, [$index + 1, $row['buch_no'], $row['tree_wt'], $row['tree_bhuko'], $row['remaining_tree_wt'], $row['office_at']]);
            }
            fputcsv($handle, ['Total', '', $history['tree_cutting_office']['totals']['tree_wt'], $history['tree_cutting_office']['totals']['tree_bhuko'], $history['tree_cutting_office']['totals']['remaining_tree_wt'], '']);
            fputcsv($handle, []);

            fputcsv($handle, ['5. Tree Cutting Issue']);
            fputcsv($handle, ['Sr No', 'Buch No', 'Worker', 'Receive Tree Wt', 'Issued At']);
            foreach ($history['tree_cutting_issue']['rows'] as $index => $row) {
                fputcsv($handle, [$index + 1, $row['buch_no'], $row['worker'], $row['receive_tree_wt'], $row['issued_at']]);
            }
            fputcsv($handle, ['Total', '', '', $history['tree_cutting_issue']['totals']['receive_tree_wt'], '']);
            fputcsv($handle, []);

            fputcsv($handle, ['6. Tree Cutting Receive']);
            fputcsv($handle, ['Sr No', 'Buch No', 'Worker', 'Receive Pc Wt', 'Tree Bhuko', 'Loss', 'Received At']);
            foreach ($history['tree_cutting_receive']['rows'] as $index => $row) {
                fputcsv($handle, [$index + 1, $row['buch_no'], $row['worker'], $row['receive_pc_wt'], $row['receive_tree_bhuko'], $row['loss'], $row['received_at']]);
            }
            fputcsv($handle, ['Total', '', '', $history['tree_cutting_receive']['totals']['receive_pc_wt'], $history['tree_cutting_receive']['totals']['receive_tree_bhuko'], $history['tree_cutting_receive']['totals']['loss'], '']);
            fputcsv($handle, []);

            fputcsv($handle, ['7. Casting Sorting']);
            fputcsv($handle, ['Sr No', 'Item', 'Weight', 'Quantity', 'Sorted At']);
            foreach ($history['casting_sorting']['rows'] as $index => $row) {
                fputcsv($handle, [$index + 1, $row['item'], $row['weight'], $row['quantity'], $row['sorted_at']]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function voucherOptionsQuery(Request $request, int $companyId): Builder
    {
        return VacuumVoucher::query()
            ->where('company_id', $companyId)
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = trim((string) $request->input('q'));
                $query->where('voucher_no', 'like', "%{$search}%");
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where('voucher_no', 'like', "%{$search}%");
            })
            ->latest('id')
            ->select(['id', 'voucher_no', 'voucher_date', 'created_at']);
    }

    private function findVoucher(int $companyId, int $id): ?VacuumVoucher
    {
        return VacuumVoucher::where('company_id', $companyId)
            ->with(['process:id,name', 'jobWorker:id,name', 'createdByUser:id,name', 'items'])
            ->find($id);
    }

    private function historyForVoucher(int $companyId, VacuumVoucher $voucher): array
    {
        $validItemIds = $voucher->items->pluck('id')->map(fn($id) => (int) $id)->all();

        $heatingItems = CastingHeatingItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->when($validItemIds !== [], fn($query) => $query->whereIn('vacuum_voucher_item_id', $validItemIds))
            ->with('voucherItem:id,buch_no,silver_wt')
            ->orderBy('id')
            ->get();

        $metalIssueItems = CastingMetalIssueItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->when($validItemIds !== [], fn($query) => $query->whereIn('vacuum_voucher_item_id', $validItemIds))
            ->with('voucherItem:id,buch_no')
            ->orderBy('id')
            ->get();

        $releaseItems = CastingReleaseItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->when($validItemIds !== [], fn($query) => $query->whereIn('vacuum_voucher_item_id', $validItemIds))
            ->with('voucherItem:id,buch_no')
            ->orderBy('id')
            ->get();

        $officeItems = TreeCuttingOfficeItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->when($validItemIds !== [], fn($query) => $query->whereIn('vacuum_voucher_item_id', $validItemIds))
            ->with('voucherItem:id,buch_no')
            ->orderBy('id')
            ->get();

        $treeIssueItems = TreeCuttingIssueItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($query) use ($validItemIds) {
                $query->where('is_custom', true);

                if ($validItemIds !== []) {
                    $query->orWhereIn('vacuum_voucher_item_id', $validItemIds);
                }
            })
            ->with(['voucherItem:id,buch_no', 'jobWorker:id,name'])
            ->orderBy('is_custom')
            ->orderBy('id')
            ->get();

        $treeReceiveItems = TreeCuttingReceiveItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($query) use ($validItemIds) {
                $query->where('is_custom', true);

                if ($validItemIds !== []) {
                    $query->orWhereIn('vacuum_voucher_item_id', $validItemIds);
                }
            })
            ->with(['voucherItem:id,buch_no', 'jobWorker:id,name'])
            ->orderBy('is_custom')
            ->orderBy('id')
            ->get();

        $sortingItems = CastingSortingItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->with('item:id,item_name,item_code')
            ->orderBy('id')
            ->get();

        return [
            'summary' => [
                'total_pcs' => $voucher->items->count(),
                'gross_wt' => $this->decimal($voucher->gross_wt_total),
                'buch_wt' => $this->decimal($voucher->buch_wt_total),
                'net_wt' => $this->decimal($voucher->net_wt_total),
                'silver_wt' => $this->decimal($voucher->silver_wt_total),
            ],
            'casting_heating' => [
                'in_bhati_count' => $heatingItems->where('in_bhati', true)->count(),
                'total_count' => $heatingItems->count(),
                'rows' => $heatingItems->map(fn($item) => [
                    'id' => (int) $item->id,
                    'vacuum_voucher_item_id' => (int) $item->vacuum_voucher_item_id,
                    'buch_no' => $item->voucherItem?->buch_no ?? '-',
                    'in_bhati' => (bool) $item->in_bhati,
                    'in_bhati_view' => $item->in_bhati ? 'Yes' : 'No',
                    'checked_at' => $this->dateTimeValue($item->checked_at ?: $item->created_at),
                    'checked_at_view' => $this->dateTime($item->checked_at ?: $item->created_at),
                ])->values(),
            ],
            'casting_metal_issue' => [
                'issued_count' => $metalIssueItems->count(),
                'silver_wt_total' => $this->decimal($metalIssueItems->sum(fn($item) => (float) ($item->voucherItem?->silver_wt ?? 0))),
                'issue_silver_wt_total' => $this->decimal($metalIssueItems->sum(fn($item) => (float) ($item->issue_silver_wt ?? 0))),
                'metal_weight_total' => $this->decimal($metalIssueItems->sum(fn($item) => (float) ($item->metal_weight ?? 0))),
                'rows' => $metalIssueItems->map(fn($item) => [
                    'id' => (int) $item->id,
                    'vacuum_voucher_item_id' => (int) $item->vacuum_voucher_item_id,
                    'buch_no' => $item->voucherItem?->buch_no ?? '-',
                    'is_if' => (bool) $item->is_if,
                    'silver_wt' => $this->decimal($item->voucherItem?->silver_wt),
                    'pure_fine' => $this->decimal($item->pure_fine),
                    'if_percentage' => $this->decimal($item->if_percentage, 2),
                    'other_metal' => $this->decimal($item->other_metal),
                    'metal_weight' => $this->decimal($item->metal_weight),
                    'issue_silver_wt' => $this->decimal($item->issue_silver_wt),
                    'remarks' => $item->remarks,
                    'issued_at' => $this->dateTimeValue($item->issued_at ?: $item->created_at),
                    'issued_at_view' => $this->dateTime($item->issued_at ?: $item->created_at),
                ])->values(),
            ],
            'casting_receive' => [
                'received_count' => $releaseItems->count(),
                'release_tree_wt_total' => $this->decimal($releaseItems->sum(fn($item) => (float) ($item->release_tree_wt ?? 0))),
                'release_tree_bhuko_total' => $this->decimal($releaseItems->sum(fn($item) => (float) ($item->release_tree_bhuko ?? 0))),
                'loss_total' => $this->decimal($releaseItems->sum(fn($item) => (float) ($item->loss ?? 0))),
                'rows' => $releaseItems->map(fn($item) => [
                    'id' => (int) $item->id,
                    'vacuum_voucher_item_id' => (int) $item->vacuum_voucher_item_id,
                    'buch_no' => $item->voucherItem?->buch_no ?? '-',
                    'release_tree_wt' => $this->decimal($item->release_tree_wt),
                    'release_tree_bhuko' => $this->decimal($item->release_tree_bhuko),
                    'loss' => $this->decimal($item->loss),
                    'released_at' => $this->dateTimeValue($item->released_at ?: $item->created_at),
                    'released_at_view' => $this->dateTime($item->released_at ?: $item->created_at),
                ])->values(),
            ],
            'tree_cutting_office' => [
                'office_count' => $officeItems->count(),
                'tree_wt_total' => $this->decimal($officeItems->sum(fn($item) => (float) ($item->tree_wt ?? 0))),
                'tree_bhuko_total' => $this->decimal($officeItems->sum(fn($item) => (float) ($item->office_cut_wt ?? 0))),
                'remaining_tree_wt_total' => $this->decimal($officeItems->sum(fn($item) => (float) ($item->remaining_tree_wt ?? 0))),
                'rows' => $officeItems->map(fn($item) => [
                    'id' => (int) $item->id,
                    'vacuum_voucher_item_id' => (int) $item->vacuum_voucher_item_id,
                    'buch_no' => $item->voucherItem?->buch_no ?? '-',
                    'tree_wt' => $this->decimal($item->tree_wt),
                    'tree_bhuko' => $this->decimal($item->office_cut_wt),
                    'remaining_tree_wt' => $this->decimal($item->remaining_tree_wt),
                    'office_at' => $this->dateTimeValue($item->office_cut_at ?: $item->created_at),
                    'office_at_view' => $this->dateTime($item->office_cut_at ?: $item->created_at),
                ])->values(),
            ],
            'tree_cutting_issue' => [
                'issued_count' => $treeIssueItems->count(),
                'receive_tree_wt_total' => $this->decimal($treeIssueItems->sum(fn($item) => (float) ($item->receive_tree_wt ?? 0))),
                'rows' => $treeIssueItems->map(fn($item) => [
                    'id' => (int) $item->id,
                    'vacuum_voucher_item_id' => $item->vacuum_voucher_item_id ? (int) $item->vacuum_voucher_item_id : null,
                    'buch_no' => $this->buchNo($item),
                    'is_custom' => (bool) $item->is_custom,
                    'job_worker_id' => $item->job_worker_id ? (int) $item->job_worker_id : null,
                    'worker_name' => $item->jobWorker?->name ?? '-',
                    'receive_tree_wt' => $this->decimal($item->receive_tree_wt),
                    'issued_at' => $this->dateTimeValue($item->issued_at ?: $item->created_at),
                    'issued_at_view' => $this->dateTime($item->issued_at ?: $item->created_at),
                ])->values(),
            ],
            'tree_cutting_receive' => [
                'received_count' => $treeReceiveItems->count(),
                'receive_pc_wt_total' => $this->decimal($treeReceiveItems->sum(fn($item) => (float) ($item->receive_pc_wt ?? 0))),
                'receive_tree_bhuko_total' => $this->decimal($treeReceiveItems->sum(fn($item) => (float) ($item->receive_tree_bhuko ?? 0))),
                'loss_total' => $this->decimal($treeReceiveItems->sum(fn($item) => (float) ($item->loss ?? 0))),
                'rows' => $treeReceiveItems->map(fn($item) => [
                    'id' => (int) $item->id,
                    'vacuum_voucher_item_id' => $item->vacuum_voucher_item_id ? (int) $item->vacuum_voucher_item_id : null,
                    'tree_cutting_issue_item_id' => $item->tree_cutting_issue_item_id ? (int) $item->tree_cutting_issue_item_id : null,
                    'buch_no' => $this->buchNo($item),
                    'is_custom' => (bool) $item->is_custom,
                    'job_worker_id' => $item->job_worker_id ? (int) $item->job_worker_id : null,
                    'worker_name' => $item->jobWorker?->name ?? '-',
                    'receive_pc_wt' => $this->decimal($item->receive_pc_wt),
                    'receive_tree_bhuko' => $this->decimal($item->receive_tree_bhuko),
                    'loss' => $this->decimal($item->loss),
                    'received_at' => $this->dateTimeValue($item->received_at ?: $item->created_at),
                    'received_at_view' => $this->dateTime($item->received_at ?: $item->created_at),
                ])->values(),
            ],
            'casting_sorting' => [
                'sorting_count' => $sortingItems->count(),
                'sorting_wt_total' => $this->decimal($sortingItems->sum(fn($item) => (float) ($item->weight ?? 0))),
                'quantity_total' => (int) $sortingItems->sum(fn($item) => (int) ($item->quantity ?? 0)),
                'rows' => $sortingItems->map(fn($item) => [
                    'id' => (int) $item->id,
                    'item_id' => $item->item_id ? (int) $item->item_id : null,
                    'item_name' => $item->item?->item_name,
                    'item_code' => $item->item?->item_code,
                    'item' => trim(($item->item?->item_name ?? '-') . ($item->item?->item_code ? ' - ' . $item->item->item_code : '')),
                    'weight' => $this->decimal($item->weight),
                    'quantity' => $item->quantity !== null ? (int) $item->quantity : null,
                    'sorted_at' => $this->dateTimeValue($item->sorted_at ?: $item->created_at),
                    'sorted_at_view' => $this->dateTime($item->sorted_at ?: $item->created_at),
                ])->values(),
            ],
        ];
    }

    private function historyForPdf(int $companyId, VacuumVoucher $voucher): array
    {
        $history = $this->historyForVoucher($companyId, $voucher);

        return [
            'summary' => $history['summary'],
            'casting_heating' => [
                'in_bhati_count' => $history['casting_heating']['in_bhati_count'],
                'total_count' => $history['casting_heating']['total_count'],
                'rows' => $history['casting_heating']['rows']->map(fn($row) => [
                    'buch_no' => $row['buch_no'],
                    'in_bhati' => $row['in_bhati_view'],
                    'checked_at' => $row['checked_at_view'] ?? '-',
                ]),
            ],
            'casting_metal_issue' => [
                'totals' => [
                    'silver_wt' => $history['casting_metal_issue']['silver_wt_total'],
                    'issue_silver_wt' => $history['casting_metal_issue']['issue_silver_wt_total'],
                ],
                'rows' => $history['casting_metal_issue']['rows']->map(fn($row) => [
                    'buch_no' => $row['buch_no'],
                    'silver_wt' => $row['silver_wt'],
                    'issue_silver_wt' => $row['issue_silver_wt'],
                    'issued_at' => $row['issued_at_view'] ?? '-',
                ]),
            ],
            'casting_receive' => [
                'totals' => [
                    'release_tree_wt' => $history['casting_receive']['release_tree_wt_total'],
                    'release_tree_bhuko' => $history['casting_receive']['release_tree_bhuko_total'],
                    'loss' => $history['casting_receive']['loss_total'],
                ],
                'rows' => $history['casting_receive']['rows']->map(fn($row) => [
                    'buch_no' => $row['buch_no'],
                    'release_tree_wt' => $row['release_tree_wt'],
                    'release_tree_bhuko' => $row['release_tree_bhuko'],
                    'loss' => $row['loss'],
                    'received_at' => $row['released_at_view'] ?? '-',
                ]),
            ],
            'tree_cutting_office' => [
                'totals' => [
                    'tree_wt' => $history['tree_cutting_office']['tree_wt_total'],
                    'tree_bhuko' => $history['tree_cutting_office']['tree_bhuko_total'],
                    'remaining_tree_wt' => $history['tree_cutting_office']['remaining_tree_wt_total'],
                ],
                'rows' => $history['tree_cutting_office']['rows']->map(fn($row) => [
                    'buch_no' => $row['buch_no'],
                    'tree_wt' => $row['tree_wt'],
                    'tree_bhuko' => $row['tree_bhuko'],
                    'remaining_tree_wt' => $row['remaining_tree_wt'],
                    'office_at' => $row['office_at_view'] ?? '-',
                ]),
            ],
            'tree_cutting_issue' => [
                'totals' => [
                    'receive_tree_wt' => $history['tree_cutting_issue']['receive_tree_wt_total'],
                ],
                'rows' => $history['tree_cutting_issue']['rows']->map(fn($row) => [
                    'buch_no' => $row['buch_no'],
                    'worker' => $row['worker_name'],
                    'receive_tree_wt' => $row['receive_tree_wt'],
                    'issued_at' => $row['issued_at_view'] ?? '-',
                ]),
            ],
            'tree_cutting_receive' => [
                'totals' => [
                    'receive_pc_wt' => $history['tree_cutting_receive']['receive_pc_wt_total'],
                    'receive_tree_bhuko' => $history['tree_cutting_receive']['receive_tree_bhuko_total'],
                    'loss' => $history['tree_cutting_receive']['loss_total'],
                ],
                'rows' => $history['tree_cutting_receive']['rows']->map(fn($row) => [
                    'buch_no' => $row['buch_no'],
                    'worker' => $row['worker_name'],
                    'receive_pc_wt' => $row['receive_pc_wt'],
                    'receive_tree_bhuko' => $row['receive_tree_bhuko'],
                    'loss' => $row['loss'],
                    'received_at' => $row['received_at_view'] ?? '-',
                ]),
            ],
            'casting_sorting' => [
                'rows' => $history['casting_sorting']['rows']->map(fn($row) => [
                    'item' => $row['item'],
                    'weight' => $row['weight'],
                    'quantity' => $row['quantity'] ?? '-',
                    'sorted_at' => $row['sorted_at_view'] ?? '-',
                ]),
            ],
        ];
    }

    private function voucherPayload(VacuumVoucher $voucher): array
    {
        return [
            'id' => (int) $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'voucher_date' => optional($voucher->voucher_date)->format('Y-m-d'),
            'voucher_date_view' => optional($voucher->voucher_date)->format('d-m-Y'),
            'date_time' => optional($voucher->created_at)->format('d-m-Y / h:i A'),
            'process_datetime' => optional($voucher->created_at)->format('Y-m-d H:i:s'),
            'process_id' => (int) $voucher->vacuum_process_id,
            'process_name' => $voucher->process?->name,
            'worker_id' => (int) $voucher->job_worker_id,
            'worker_name' => $voucher->jobWorker?->name,
            'formula_value' => $this->decimal($voucher->formula_value),
            'gross_wt_total' => $this->decimal($voucher->gross_wt_total),
            'buch_wt_total' => $this->decimal($voucher->buch_wt_total),
            'net_wt_total' => $this->decimal($voucher->net_wt_total),
            'silver_wt_total' => $this->decimal($voucher->silver_wt_total),
            'total_pcs' => $voucher->items->count(),
            'remarks' => $voucher->remarks,
            'created_by' => $voucher->created_by ? (int) $voucher->created_by : null,
            'created_by_name' => $voucher->createdByUser?->name,
            'created_at' => optional($voucher->created_at)->format('Y-m-d H:i:s'),
            'created_at_view' => optional($voucher->created_at)->format('d-m-Y / h:i A'),
        ];
    }

    private function voucherOption(VacuumVoucher $voucher): array
    {
        return [
            'id' => (int) $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'voucher_date' => optional($voucher->voucher_date)->format('Y-m-d'),
            'voucher_date_view' => optional($voucher->voucher_date)->format('d-m-Y'),
            'created_at' => optional($voucher->created_at)->format('Y-m-d H:i:s'),
            'created_at_view' => optional($voucher->created_at)->format('d-m-Y / h:i A'),
            'name' => trim($voucher->voucher_no . ' - ' . optional($voucher->voucher_date)->format('d-m-Y')),
        ];
    }

    private function buchNo($item): string
    {
        if ($item->is_custom && $item->custom_buch_no) {
            return $item->custom_buch_no;
        }

        return $item->voucherItem?->buch_no ?? '-';
    }

    private function decimal($value, int $precision = 3): string
    {
        return number_format((float) ($value ?? 0), $precision, '.', '');
    }

    private function dateTime($value): ?string
    {
        return $value ? \Carbon\Carbon::parse($value)->format('d-m-Y h:i A') : null;
    }

    private function dateTimeValue($value): ?string
    {
        return $value ? \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s') : null;
    }

    private function limit(Request $request): int
    {
        return min(max((int) $request->input('limit', 300), 1), 500);
    }
}
