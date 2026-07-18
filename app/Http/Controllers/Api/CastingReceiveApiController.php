<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CastingMetalIssueItem;
use App\Models\CastingReleaseItem;
use App\Models\Company;
use App\Models\VacuumVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CastingReceiveApiController extends Controller
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
                    ->from('casting_metal_issue_items')
                    ->whereColumn('casting_metal_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_metal_issue_items.company_id', $companyId)
                    ->when($fromDate, fn($q) => $q->whereDate(DB::raw('COALESCE(casting_metal_issue_items.issued_at, casting_metal_issue_items.created_at)'), '>=', $fromDate))
                    ->when($toDate, fn($q) => $q->whereDate(DB::raw('COALESCE(casting_metal_issue_items.issued_at, casting_metal_issue_items.created_at)'), '<=', $toDate));
            })
            ->when($request->filled('worker_id'), fn($query) => $query->where('job_worker_id', (int) $request->input('worker_id')))
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
                $query->from('casting_metal_issue_items')
                    ->selectRaw('MAX(COALESCE(casting_metal_issue_items.issued_at, casting_metal_issue_items.created_at))')
                    ->whereColumn('casting_metal_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_metal_issue_items.company_id', $companyId);
            }, 'metal_issue_datetime')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_metal_issue_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('casting_metal_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_metal_issue_items.company_id', $companyId);
            }, 'metal_issue_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_release_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_release_items.company_id', $companyId)
                    ->where(function ($q) {
                        $q->whereNotNull('casting_release_items.release_tree_wt')
                            ->orWhereNotNull('casting_release_items.release_tree_bhuko');
                    });
            }, 'assigned_receive_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_release_items')
                    ->selectRaw('MAX(COALESCE(casting_release_items.released_at, casting_release_items.created_at))')
                    ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_release_items.company_id', $companyId);
            }, 'release_datetime')
            ->orderByDesc('metal_issue_datetime')
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                $total = (int) ($row->metal_issue_count ?? 0);
                $assigned = (int) ($row->assigned_receive_count ?? 0);

                return [
                    'id' => (int) $row->id,
                    'voucher_no' => $row->voucher_no,
                    'voucher_date' => optional($row->voucher_date)->format('Y-m-d'),
                    'date_time' => $row->release_datetime ? \Carbon\Carbon::parse($row->release_datetime)->format('d-m-Y / h:i A') : ($row->metal_issue_datetime ? \Carbon\Carbon::parse($row->metal_issue_datetime)->format('d-m-Y / h:i A') : null),
                    'process_datetime' => $row->release_datetime ? \Carbon\Carbon::parse($row->release_datetime)->format('Y-m-d H:i:s') : ($row->metal_issue_datetime ? \Carbon\Carbon::parse($row->metal_issue_datetime)->format('Y-m-d H:i:s') : null),
                    'process_id' => (int) $row->vacuum_process_id,
                    'process_name' => $row->process?->name,
                    'worker_id' => (int) $row->job_worker_id,
                    'worker_name' => $row->jobWorker?->name,
                    'assigned_receive' => $assigned,
                    'pending_receive' => max($total - $assigned, 0),
                    'metal_issue_count' => $total,
                    'metal_issue_datetime' => $row->metal_issue_datetime,
                    'release_datetime' => $row->release_datetime,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function show(Request $request, $id)
    {
        $voucher = $this->findVoucher($request, (int) $id);

        if (!$voucher || $voucher->metalIssueItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Casting Receive voucher not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatVoucher($voucher),
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $voucher = VacuumVoucher::where('company_id', $companyId)
            ->with(['items:id,vacuum_voucher_id', 'metalIssueItems'])
            ->find((int) $id);

        if (!$voucher || $voucher->metalIssueItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Casting Receive voucher not found',
            ], 404);
        }

        $validItemIds = $voucher->items->pluck('id')->map(fn($itemId) => (int) $itemId)->all();
        $issueItems = $voucher->metalIssueItems->keyBy('vacuum_voucher_item_id');

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.release_tree_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.release_tree_bhuko' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $companyId, $voucher, $validItemIds, $issueItems, $validated) {
            foreach (($validated['items'] ?? []) as $itemId => $row) {
                $itemId = (int) $itemId;

                if (!in_array($itemId, $validItemIds, true)) {
                    continue;
                }

                $issueItem = $issueItems->get($itemId);
                if (!$issueItem) {
                    continue;
                }

                $issueSilverWt = (float) ($issueItem->issue_silver_wt ?? 0);
                $releaseTreeWt = $row['release_tree_wt'] ?? null;
                $releaseTreeBhuko = $row['release_tree_bhuko'] ?? null;
                $releaseTreeWtValue = $releaseTreeWt !== null && $releaseTreeWt !== '' ? (float) $releaseTreeWt : null;
                $releaseTreeBhukoValue = $releaseTreeBhuko !== null && $releaseTreeBhuko !== '' ? (float) $releaseTreeBhuko : null;
                $loss = $releaseTreeWtValue !== null || $releaseTreeBhukoValue !== null
                    ? round(($releaseTreeWtValue ?? 0) + ($releaseTreeBhukoValue ?? 0) - $issueSilverWt, 3)
                    : null;

                CastingReleaseItem::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'vacuum_voucher_item_id' => $itemId,
                    ],
                    [
                        'vacuum_voucher_id' => $voucher->id,
                        'release_tree_wt' => $releaseTreeWtValue,
                        'release_tree_bhuko' => $releaseTreeBhukoValue,
                        'loss' => $loss,
                        'released_by' => (int) $request->user()->id,
                        'released_at' => now(),
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Casting Receive updated successfully',
            'data' => $this->formatVoucher($this->findVoucher($request, (int) $voucher->id)),
        ]);
    }

    public function pdf(Request $request, $id)
    {
        $voucher = $this->findVoucher($request, (int) $id);

        if (!$voucher || $voucher->metalIssueItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Casting Receive voucher not found',
            ], 404);
        }

        $company = Company::findOrFail((int) $request->user()->company_id);
        $issueItems = $voucher->metalIssueItems->keyBy('vacuum_voucher_item_id');
        $releaseItems = $voucher->releaseItems->keyBy('vacuum_voucher_item_id');

        return Pdf::loadView('company.casting_release.pdf.show', compact('company', 'voucher', 'issueItems', 'releaseItems'))
            ->setPaper('a4', 'portrait')
            ->download('casting_receive_' . $voucher->voucher_no . '.pdf');
    }

    private function findVoucher(Request $request, int $id): ?VacuumVoucher
    {
        return VacuumVoucher::where('company_id', (int) $request->user()->company_id)
            ->where('id', $id)
            ->with(['process:id,name', 'jobWorker:id,name', 'items', 'metalIssueItems', 'releaseItems'])
            ->withCount('items')
            ->first();
    }

    private function formatVoucher(VacuumVoucher $voucher): array
    {
        $issueItems = $voucher->metalIssueItems->keyBy('vacuum_voucher_item_id');
        $releaseItems = $voucher->releaseItems->keyBy('vacuum_voucher_item_id');
        $assigned = $releaseItems->filter(fn($item) => $item->release_tree_wt !== null || $item->release_tree_bhuko !== null)->count();
        $total = $issueItems->count();
        $processDateTime = $this->latestProcessDateTime($voucher->releaseItems, 'released_at')
            ?: $this->latestProcessDateTime($voucher->metalIssueItems, 'issued_at')
            ?: $voucher->created_at;

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
            'total_pcs' => $total,
            'assigned_receive' => $assigned,
            'pending_receive' => max($total - $assigned, 0),
            'created_at' => optional($voucher->created_at)->format('Y-m-d H:i:s'),
            'created_at_view' => optional($voucher->created_at)->format('d-m-Y / h:i A'),
            'items' => $voucher->items
                ->filter(fn($item) => $issueItems->has($item->id))
                ->map(function ($item) use ($issueItems, $releaseItems) {
                    $issueItem = $issueItems->get($item->id);
                    $releaseItem = $releaseItems->get($item->id);

                    return [
                        'id' => (int) $item->id,
                        'vacuum_voucher_item_id' => (int) $item->id,
                        'vacuum_buch_id' => $item->vacuum_buch_id ? (int) $item->vacuum_buch_id : null,
                        'buch_no' => $item->buch_no,
                        'issue_silver_wt' => $this->decimalValue($issueItem?->issue_silver_wt, 3),
                        'release_tree_wt' => $releaseItem?->release_tree_wt !== null ? $this->decimalValue($releaseItem->release_tree_wt, 3) : null,
                        'release_tree_bhuko' => $releaseItem?->release_tree_bhuko !== null ? $this->decimalValue($releaseItem->release_tree_bhuko, 3) : null,
                        'loss' => $releaseItem?->loss !== null ? $this->decimalValue($releaseItem->loss, 3) : null,
                        'released_by' => $releaseItem?->released_by ? (int) $releaseItem->released_by : null,
                        'released_at' => optional($releaseItem?->released_at)->format('Y-m-d H:i:s'),
                        'released_at_view' => optional($releaseItem?->released_at)->format('d-m-Y / h:i A'),
                    ];
                })
                ->values(),
        ];
    }

    private function decimalValue($value, int $precision): string
    {
        return number_format((float) ($value ?? 0), $precision, '.', '');
    }

    private function latestProcessDateTime($rows, string $preferredColumn)
    {
        return $rows
            ->map(fn($row) => $row->{$preferredColumn} ?: $row->created_at)
            ->filter()
            ->sortDesc()
            ->first();
    }
}
