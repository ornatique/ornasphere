<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\TreeCuttingIssueItem;
use App\Models\TreeCuttingReceiveItem;
use App\Models\VacuumVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreeCuttingReceiveApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $fromDate = $request->input('from_date', now()->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());

        $rows = VacuumVoucher::query()
            ->where('company_id', $companyId)
            ->whereExists(function ($query) use ($companyId, $fromDate, $toDate) {
                $query->selectRaw('1')
                    ->from('tree_cutting_issue_items')
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId)
                    ->whereNotNull('tree_cutting_issue_items.receive_tree_wt')
                    ->when($fromDate, fn($q) => $q->whereDate(DB::raw('COALESCE(tree_cutting_issue_items.issued_at, tree_cutting_issue_items.created_at)'), '>=', $fromDate))
                    ->when($toDate, fn($q) => $q->whereDate(DB::raw('COALESCE(tree_cutting_issue_items.issued_at, tree_cutting_issue_items.created_at)'), '<=', $toDate));
            })
            ->when($request->filled('worker_id'), function ($query) use ($companyId, $request) {
                $query->whereExists(function ($q) use ($companyId, $request) {
                    $q->selectRaw('1')
                        ->from('tree_cutting_issue_items')
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $companyId)
                        ->where('tree_cutting_issue_items.job_worker_id', (int) $request->input('worker_id'))
                        ->whereNotNull('tree_cutting_issue_items.receive_tree_wt');
                });
            })
            ->when($request->filled('process_id'), fn($query) => $query->where('vacuum_process_id', (int) $request->input('process_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('voucher_no', 'like', "%{$search}%")
                        ->orWhereHas('process', fn($process) => $process->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('jobWorker', fn($worker) => $worker->where('name', 'like', "%{$search}%"));
                });
            })
            ->with(['process:id,name', 'jobWorker:id,name'])
            ->select('vacuum_vouchers.*')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_issue_items')
                    ->selectRaw('MAX(COALESCE(tree_cutting_issue_items.issued_at, tree_cutting_issue_items.created_at))')
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId);
            }, 'tree_cutting_issue_datetime')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_receive_items')
                    ->selectRaw('MAX(COALESCE(tree_cutting_receive_items.received_at, tree_cutting_receive_items.created_at))')
                    ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_receive_items.company_id', $companyId);
            }, 'tree_cutting_receive_datetime')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_issue_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId)
                    ->whereNotNull('tree_cutting_issue_items.receive_tree_wt');
            }, 'tree_cutting_issue_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_receive_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_receive_items.company_id', $companyId)
                    ->where(function ($q) {
                        $q->whereNotNull('tree_cutting_receive_items.receive_pc_wt')
                            ->orWhereNotNull('tree_cutting_receive_items.receive_tree_bhuko');
                    });
            }, 'tree_cutting_receive_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_receive_items')
                    ->selectRaw('COALESCE(SUM(tree_cutting_receive_items.receive_pc_wt), 0)')
                    ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_receive_items.company_id', $companyId);
            }, 'receive_pc_wt_total')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_receive_items')
                    ->selectRaw('COALESCE(SUM(tree_cutting_receive_items.receive_tree_bhuko), 0)')
                    ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_receive_items.company_id', $companyId);
            }, 'receive_tree_bhuko_total')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_receive_items')
                    ->selectRaw('COALESCE(SUM(tree_cutting_receive_items.loss), 0)')
                    ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_receive_items.company_id', $companyId);
            }, 'loss_total')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_issue_items')
                    ->leftJoin('job_workers', 'job_workers.id', '=', 'tree_cutting_issue_items.job_worker_id')
                    ->selectRaw("GROUP_CONCAT(DISTINCT job_workers.name ORDER BY job_workers.name SEPARATOR ', ')")
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId)
                    ->whereNotNull('tree_cutting_issue_items.receive_tree_wt');
            }, 'tree_cutting_worker_names')
            ->orderByDesc('tree_cutting_issue_datetime')
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                $issue = (int) ($row->tree_cutting_issue_count ?? 0);
                $received = (int) ($row->tree_cutting_receive_count ?? 0);
                $processDateTime = $row->tree_cutting_receive_datetime ?: $row->tree_cutting_issue_datetime;

                return [
                    'id' => (int) $row->id,
                    'voucher_no' => $row->voucher_no,
                    'voucher_date' => optional($row->voucher_date)->format('Y-m-d'),
                    'date_time' => $processDateTime ? \Carbon\Carbon::parse($processDateTime)->format('d-m-Y / h:i A') : null,
                    'process_datetime' => $processDateTime ? \Carbon\Carbon::parse($processDateTime)->format('Y-m-d H:i:s') : null,
                    'process_id' => (int) $row->vacuum_process_id,
                    'process_name' => $row->process?->name,
                    'worker_id' => (int) $row->job_worker_id,
                    'worker_name' => $row->tree_cutting_worker_names ?: ($row->jobWorker?->name),
                    'assigned_receive' => $received,
                    'pending_receive' => max($issue - $received, 0),
                    'tree_cutting_issue_count' => $issue,
                    'receive_pc_wt_total' => $this->decimalValue($row->receive_pc_wt_total, 3),
                    'receive_tree_bhuko_total' => $this->decimalValue($row->receive_tree_bhuko_total, 3),
                    'loss_total' => $this->decimalValue($row->loss_total, 3),
                ];
            })
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function show(Request $request, $id)
    {
        $data = $this->voucherData($request, (int) $id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Tree Cutting Receive voucher not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatVoucher(...$data)]);
    }

    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $voucher = VacuumVoucher::where('company_id', $companyId)->find((int) $id);

        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Tree Cutting Receive voucher not found'], 404);
        }

        $issueItems = TreeCuttingIssueItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->whereNotNull('receive_tree_wt')
            ->get()
            ->keyBy('id');

        if ($issueItems->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Tree Cutting Receive voucher not found'], 404);
        }

        $validItemIds = $issueItems->keys()->map(fn($itemId) => (int) $itemId)->all();

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.receive_pc_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.receive_tree_bhuko' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $companyId, $voucher, $issueItems, $validItemIds, $validated) {
            foreach (($validated['items'] ?? []) as $issueItemId => $row) {
                $issueItemId = (int) $issueItemId;

                if (!in_array($issueItemId, $validItemIds, true)) {
                    continue;
                }

                $issueItem = $issueItems->get($issueItemId);
                $issueTreeWt = (float) ($issueItem?->receive_tree_wt ?? 0);
                $receivePcWt = $row['receive_pc_wt'] ?? null;
                $receiveTreeBhuko = $row['receive_tree_bhuko'] ?? null;
                $receivePcWtValue = $receivePcWt !== null && $receivePcWt !== '' ? (float) $receivePcWt : null;
                $receiveTreeBhukoValue = $receiveTreeBhuko !== null && $receiveTreeBhuko !== '' ? (float) $receiveTreeBhuko : null;
                $loss = $receivePcWtValue !== null || $receiveTreeBhukoValue !== null
                    ? round(($receivePcWtValue ?? 0) + ($receiveTreeBhukoValue ?? 0) - $issueTreeWt, 3)
                    : null;

                TreeCuttingReceiveItem::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'tree_cutting_issue_item_id' => $issueItemId,
                    ],
                    [
                        'vacuum_voucher_id' => $voucher->id,
                        'vacuum_voucher_item_id' => $issueItem?->vacuum_voucher_item_id,
                        'job_worker_id' => $issueItem?->job_worker_id,
                        'custom_buch_no' => $issueItem?->custom_buch_no,
                        'is_custom' => (bool) ($issueItem?->is_custom),
                        'receive_pc_wt' => $receivePcWtValue,
                        'receive_tree_bhuko' => $receiveTreeBhukoValue,
                        'loss' => $loss,
                        'received_by' => (int) $request->user()->id,
                        'received_at' => now(),
                    ]
                );
            }
        });

        $data = $this->voucherData($request, (int) $voucher->id);

        return response()->json([
            'success' => true,
            'message' => 'Tree Cutting Receive updated successfully',
            'data' => $data ? $this->formatVoucher(...$data) : null,
        ]);
    }

    public function pdf(Request $request, $id)
    {
        $data = $this->voucherData($request, (int) $id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Tree Cutting Receive voucher not found'], 404);
        }

        [$voucher, $issueItems, $receiveItems] = $data;
        $company = Company::findOrFail((int) $request->user()->company_id);

        return Pdf::loadView('company.tree_cutting_receive.pdf.show', compact('company', 'voucher', 'issueItems', 'receiveItems'))
            ->setPaper('a4', 'portrait')
            ->download('tree_cutting_receive_' . $voucher->voucher_no . '.pdf');
    }

    private function voucherData(Request $request, int $id): ?array
    {
        $companyId = (int) $request->user()->company_id;
        $voucher = VacuumVoucher::where('company_id', $companyId)
            ->with(['process:id,name', 'jobWorker:id,name', 'items'])
            ->withCount('items')
            ->find($id);

        if (!$voucher) {
            return null;
        }

        $issueItems = TreeCuttingIssueItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->whereNotNull('receive_tree_wt')
            ->with(['voucherItem:id,buch_no', 'jobWorker:id,name'])
            ->orderBy('is_custom')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($issueItems->isEmpty()) {
            return null;
        }

        $receiveItems = TreeCuttingReceiveItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy('tree_cutting_issue_item_id');

        return [$voucher, $issueItems, $receiveItems];
    }

    private function formatVoucher(VacuumVoucher $voucher, $issueItems, $receiveItems): array
    {
        $processDateTime = $this->latestProcessDateTime($receiveItems, 'received_at')
            ?: $this->latestProcessDateTime($issueItems, 'issued_at')
            ?: $voucher->created_at;

        $rows = $issueItems->map(function ($issueItem) use ($receiveItems) {
            $receiveItem = $receiveItems->get($issueItem->id);
            $buchNo = $issueItem->is_custom ? $issueItem->custom_buch_no : ($issueItem->voucherItem?->buch_no ?? null);

            return [
                'id' => $receiveItem?->id ? (int) $receiveItem->id : null,
                'tree_cutting_issue_item_id' => (int) $issueItem->id,
                'vacuum_voucher_item_id' => $issueItem->vacuum_voucher_item_id ? (int) $issueItem->vacuum_voucher_item_id : null,
                'buch_no' => $buchNo,
                'custom_buch_no' => $issueItem->custom_buch_no,
                'is_custom' => (bool) $issueItem->is_custom,
                'job_worker_id' => $issueItem->job_worker_id ? (int) $issueItem->job_worker_id : null,
                'worker_name' => $issueItem->jobWorker?->name,
                'issue_tree_wt' => $this->decimalValue($issueItem->receive_tree_wt, 3),
                'receive_pc_wt' => $receiveItem?->receive_pc_wt !== null ? $this->decimalValue($receiveItem->receive_pc_wt, 3) : null,
                'receive_tree_bhuko' => $receiveItem?->receive_tree_bhuko !== null ? $this->decimalValue($receiveItem->receive_tree_bhuko, 3) : null,
                'loss' => $receiveItem?->loss !== null ? $this->decimalValue($receiveItem->loss, 3) : null,
                'received_by' => $receiveItem?->received_by ? (int) $receiveItem->received_by : null,
                'received_at' => optional($receiveItem?->received_at)->format('Y-m-d H:i:s'),
                'received_at_view' => optional($receiveItem?->received_at)->format('d-m-Y / h:i A'),
            ];
        })->values();

        return [
            'id' => (int) $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'voucher_date' => optional($voucher->voucher_date)->format('Y-m-d'),
            'date_time' => optional($processDateTime)->format('d-m-Y / h:i A'),
            'process_datetime' => optional($processDateTime)->format('Y-m-d H:i:s'),
            'process_id' => (int) $voucher->vacuum_process_id,
            'process_name' => $voucher->process?->name,
            'worker_id' => (int) $voucher->job_worker_id,
            'worker_name' => $voucher->jobWorker?->name,
            'total_pcs' => (int) ($voucher->items_count ?? $voucher->items->count()),
            'issue_count' => $issueItems->count(),
            'assigned_receive' => $receiveItems->filter(fn($item) => $item->receive_pc_wt !== null || $item->receive_tree_bhuko !== null)->count(),
            'receive_pc_wt_total' => $this->decimalValue($receiveItems->sum(fn($item) => (float) ($item->receive_pc_wt ?? 0)), 3),
            'receive_tree_bhuko_total' => $this->decimalValue($receiveItems->sum(fn($item) => (float) ($item->receive_tree_bhuko ?? 0)), 3),
            'loss_total' => $this->decimalValue($receiveItems->sum(fn($item) => (float) ($item->loss ?? 0)), 3),
            'created_at' => optional($voucher->created_at)->format('Y-m-d H:i:s'),
            'created_at_view' => optional($voucher->created_at)->format('d-m-Y / h:i A'),
            'items' => $rows,
        ];
    }

    private function latestProcessDateTime($rows, string $preferredColumn)
    {
        return $rows
            ->map(fn($row) => $row->{$preferredColumn} ?: $row->created_at)
            ->filter()
            ->sortDesc()
            ->first();
    }

    private function decimalValue($value, int $precision): string
    {
        return number_format((float) ($value ?? 0), $precision, '.', '');
    }
}
