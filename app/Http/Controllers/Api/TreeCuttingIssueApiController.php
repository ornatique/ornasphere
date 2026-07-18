<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CastingReleaseItem;
use App\Models\Company;
use App\Models\JobWorker;
use App\Models\TreeCuttingIssueItem;
use App\Models\VacuumVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TreeCuttingIssueApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $fromDate = $request->input('from_date', now()->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());

        $receivedRows = function ($query) use ($companyId) {
            $query->where('casting_release_items.company_id', $companyId)
                ->where(function ($q) {
                    $q->whereNotNull('casting_release_items.release_tree_wt')
                        ->orWhereNotNull('casting_release_items.release_tree_bhuko');
                });
        };

        $rows = VacuumVoucher::query()
            ->where('company_id', $companyId)
            ->whereExists(function ($query) use ($companyId, $fromDate, $toDate) {
                $query->selectRaw('1')
                    ->from('casting_release_items')
                    ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_release_items.company_id', $companyId)
                    ->where(function ($q) {
                        $q->whereNotNull('casting_release_items.release_tree_wt')
                            ->orWhereNotNull('casting_release_items.release_tree_bhuko');
                    })
                    ->when($fromDate, fn($q) => $q->whereDate(DB::raw('COALESCE(casting_release_items.released_at, casting_release_items.created_at)'), '>=', $fromDate))
                    ->when($toDate, fn($q) => $q->whereDate(DB::raw('COALESCE(casting_release_items.released_at, casting_release_items.created_at)'), '<=', $toDate));
            })
            ->when($request->filled('worker_id'), function ($query) use ($companyId, $request) {
                $workerId = (int) $request->input('worker_id');
                $query->where(function ($workerQuery) use ($companyId, $workerId) {
                    $workerQuery->where('job_worker_id', $workerId)
                        ->orWhereExists(function ($subQuery) use ($companyId, $workerId) {
                            $subQuery->selectRaw('1')
                                ->from('tree_cutting_issue_items')
                                ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                                ->where('tree_cutting_issue_items.company_id', $companyId)
                                ->where('tree_cutting_issue_items.job_worker_id', $workerId);
                        });
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
                $query->from('casting_release_items')
                    ->selectRaw('MAX(COALESCE(casting_release_items.released_at, casting_release_items.created_at))')
                    ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_release_items.company_id', $companyId);
            }, 'casting_receive_datetime')
            ->selectSub(function ($query) use ($receivedRows) {
                $query->from('casting_release_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id');
                $receivedRows($query);
            }, 'received_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_issue_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId)
                    ->whereNotNull('tree_cutting_issue_items.receive_tree_wt');
            }, 'tree_cutting_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_issue_items')
                    ->selectRaw('COALESCE(SUM(tree_cutting_issue_items.receive_tree_wt), 0)')
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId);
            }, 'receive_tree_wt_total')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_issue_items')
                    ->leftJoin('job_workers', 'job_workers.id', '=', 'tree_cutting_issue_items.job_worker_id')
                    ->selectRaw("GROUP_CONCAT(DISTINCT job_workers.name ORDER BY job_workers.name SEPARATOR ', ')")
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId);
            }, 'tree_cutting_worker_names')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_issue_items')
                    ->selectRaw('MAX(COALESCE(tree_cutting_issue_items.issued_at, tree_cutting_issue_items.created_at))')
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId);
            }, 'tree_cutting_issue_datetime')
            ->orderByDesc('casting_receive_datetime')
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                $received = (int) ($row->received_count ?? 0);
                $assigned = (int) ($row->tree_cutting_count ?? 0);
                $processDateTime = $row->tree_cutting_issue_datetime ?: $row->casting_receive_datetime;

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
                    'received_count' => $received,
                    'assigned_tree_cutting' => $assigned,
                    'pending_tree_cutting' => max($received - $assigned, 0),
                    'receive_tree_wt_total' => $this->decimalValue($row->receive_tree_wt_total, 3),
                    'casting_receive_datetime' => $row->casting_receive_datetime,
                    'tree_cutting_issue_datetime' => $row->tree_cutting_issue_datetime,
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
        $data = $this->voucherData($request, (int) $id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Tree Cutting Issue voucher not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatVoucher(...$data),
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $voucher = VacuumVoucher::where('company_id', $companyId)->find((int) $id);

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Tree Cutting Issue voucher not found',
            ], 404);
        }

        $validItemIds = CastingReleaseItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->whereNotNull('release_tree_wt')
                    ->orWhereNotNull('release_tree_bhuko');
            })
            ->pluck('vacuum_voucher_item_id')
            ->map(fn($itemId) => (int) $itemId)
            ->all();

        if ($validItemIds === []) {
            return response()->json([
                'success' => false,
                'message' => 'Tree Cutting Issue voucher not found',
            ], 404);
        }

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.receive_tree_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.job_worker_id' => [
                'nullable',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'custom_items' => ['nullable', 'array'],
            'custom_items.*.custom_buch_no' => ['nullable', 'string', 'max:255'],
            'custom_items.*.receive_tree_wt' => ['nullable', 'numeric', 'min:0'],
            'custom_items.*.job_worker_id' => [
                'nullable',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'custom_existing' => ['nullable', 'array'],
            'custom_existing.*.custom_buch_no' => ['nullable', 'string', 'max:255'],
            'custom_existing.*.receive_tree_wt' => ['nullable', 'numeric', 'min:0'],
            'custom_existing.*.job_worker_id' => [
                'nullable',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        DB::transaction(function () use ($request, $companyId, $voucher, $validItemIds, $validated) {
            foreach (($validated['items'] ?? []) as $itemId => $row) {
                $itemId = (int) $itemId;

                if (!in_array($itemId, $validItemIds, true)) {
                    continue;
                }

                $receiveTreeWt = $row['receive_tree_wt'] ?? null;
                $jobWorkerId = $row['job_worker_id'] ?? null;

                TreeCuttingIssueItem::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'vacuum_voucher_item_id' => $itemId,
                    ],
                    [
                        'vacuum_voucher_id' => $voucher->id,
                        'job_worker_id' => $jobWorkerId !== null && $jobWorkerId !== '' ? (int) $jobWorkerId : null,
                        'is_custom' => false,
                        'custom_buch_no' => null,
                        'receive_tree_wt' => $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null,
                        'issued_by' => (int) $request->user()->id,
                        'issued_at' => now(),
                    ]
                );
            }

            foreach (($validated['custom_existing'] ?? []) as $issueItemId => $row) {
                $issueItem = TreeCuttingIssueItem::where('company_id', $companyId)
                    ->where('vacuum_voucher_id', $voucher->id)
                    ->where('is_custom', true)
                    ->where('id', (int) $issueItemId)
                    ->first();

                if (!$issueItem) {
                    continue;
                }

                $customBuchNo = trim((string) ($row['custom_buch_no'] ?? ''));
                $receiveTreeWt = $row['receive_tree_wt'] ?? null;
                $jobWorkerId = $row['job_worker_id'] ?? null;

                $issueItem->update([
                    'job_worker_id' => $jobWorkerId !== null && $jobWorkerId !== '' ? (int) $jobWorkerId : null,
                    'custom_buch_no' => $customBuchNo,
                    'receive_tree_wt' => $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null,
                    'issued_by' => (int) $request->user()->id,
                    'issued_at' => now(),
                ]);
            }

            foreach (($validated['custom_items'] ?? []) as $row) {
                $customBuchNo = trim((string) ($row['custom_buch_no'] ?? ''));
                $receiveTreeWt = $row['receive_tree_wt'] ?? null;
                $jobWorkerId = $row['job_worker_id'] ?? null;

                if ($customBuchNo === '' && ($receiveTreeWt === null || $receiveTreeWt === '') && ($jobWorkerId === null || $jobWorkerId === '')) {
                    continue;
                }

                TreeCuttingIssueItem::create([
                    'company_id' => $companyId,
                    'vacuum_voucher_id' => $voucher->id,
                    'vacuum_voucher_item_id' => null,
                    'job_worker_id' => $jobWorkerId !== null && $jobWorkerId !== '' ? (int) $jobWorkerId : null,
                    'custom_buch_no' => $customBuchNo,
                    'is_custom' => true,
                    'receive_tree_wt' => $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null,
                    'issued_by' => (int) $request->user()->id,
                    'issued_at' => now(),
                ]);
            }
        });

        $data = $this->voucherData($request, (int) $voucher->id);

        return response()->json([
            'success' => true,
            'message' => 'Tree Cutting Issue updated successfully',
            'data' => $data ? $this->formatVoucher(...$data) : null,
        ]);
    }

    public function pdf(Request $request, $id)
    {
        $data = $this->voucherData($request, (int) $id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Tree Cutting Issue voucher not found',
            ], 404);
        }

        [$voucher, $receiveItems, $treeCuttingItems, $customTreeCuttingItems] = $data;
        $company = Company::findOrFail((int) $request->user()->company_id);

        return Pdf::loadView('company.tree_cutting_issue.pdf.show', compact('company', 'voucher', 'receiveItems', 'treeCuttingItems', 'customTreeCuttingItems'))
            ->setPaper('a4', 'portrait')
            ->download('tree_cutting_issue_' . $voucher->voucher_no . '.pdf');
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

        $receiveItems = CastingReleaseItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->whereNotNull('release_tree_wt')
                    ->orWhereNotNull('release_tree_bhuko');
            })
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        if ($receiveItems->isEmpty()) {
            return null;
        }

        $treeCuttingItems = TreeCuttingIssueItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where('is_custom', false)
            ->with('jobWorker:id,name')
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        $customTreeCuttingItems = TreeCuttingIssueItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where('is_custom', true)
            ->with('jobWorker:id,name')
            ->orderBy('id')
            ->get();

        return [$voucher, $receiveItems, $treeCuttingItems, $customTreeCuttingItems];
    }

    private function formatVoucher(VacuumVoucher $voucher, $receiveItems, $treeCuttingItems, $customTreeCuttingItems): array
    {
        $processDateTime = $this->latestProcessDateTime($treeCuttingItems->concat($customTreeCuttingItems), 'issued_at')
            ?: $this->latestProcessDateTime($receiveItems, 'released_at')
            ?: $voucher->created_at;

        $rows = [];
        foreach ($voucher->items as $item) {
            $receiveItem = $receiveItems->get($item->id);
            if (!$receiveItem) {
                continue;
            }

            $treeCuttingItem = $treeCuttingItems->get($item->id);
            $defaultReceiveTreeWt = $treeCuttingItem?->receive_tree_wt ?? $receiveItem?->release_tree_wt;
            $defaultWorkerId = $treeCuttingItem?->job_worker_id ?? $voucher->job_worker_id;

            $rows[] = [
                'id' => $treeCuttingItem?->id ? (int) $treeCuttingItem->id : null,
                'vacuum_voucher_item_id' => (int) $item->id,
                'vacuum_buch_id' => $item->vacuum_buch_id ? (int) $item->vacuum_buch_id : null,
                'buch_no' => $item->buch_no,
                'is_custom' => false,
                'receive_tree_wt' => $defaultReceiveTreeWt !== null ? $this->decimalValue($defaultReceiveTreeWt, 3) : null,
                'source_release_tree_wt' => $receiveItem?->release_tree_wt !== null ? $this->decimalValue($receiveItem->release_tree_wt, 3) : null,
                'source_release_tree_bhuko' => $receiveItem?->release_tree_bhuko !== null ? $this->decimalValue($receiveItem->release_tree_bhuko, 3) : null,
                'job_worker_id' => $defaultWorkerId ? (int) $defaultWorkerId : null,
                'worker_name' => $treeCuttingItem?->jobWorker?->name ?? $voucher->jobWorker?->name,
                'issued_at' => optional($treeCuttingItem?->issued_at)->format('Y-m-d H:i:s'),
                'issued_at_view' => optional($treeCuttingItem?->issued_at)->format('d-m-Y / h:i A'),
            ];
        }

        foreach ($customTreeCuttingItems as $customItem) {
            $rows[] = [
                'id' => (int) $customItem->id,
                'vacuum_voucher_item_id' => null,
                'vacuum_buch_id' => null,
                'buch_no' => $customItem->custom_buch_no,
                'custom_buch_no' => $customItem->custom_buch_no,
                'is_custom' => true,
                'receive_tree_wt' => $customItem->receive_tree_wt !== null ? $this->decimalValue($customItem->receive_tree_wt, 3) : null,
                'job_worker_id' => $customItem->job_worker_id ? (int) $customItem->job_worker_id : null,
                'worker_name' => $customItem->jobWorker?->name,
                'issued_at' => optional($customItem->issued_at)->format('Y-m-d H:i:s'),
                'issued_at_view' => optional($customItem->issued_at)->format('d-m-Y / h:i A'),
            ];
        }

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
            'received_count' => $receiveItems->count(),
            'assigned_tree_cutting' => count(array_filter($rows, fn($row) => $row['receive_tree_wt'] !== null)),
            'receive_tree_wt_total' => $this->decimalValue(collect($rows)->sum(fn($row) => (float) ($row['receive_tree_wt'] ?? 0)), 3),
            'created_at' => optional($voucher->created_at)->format('Y-m-d H:i:s'),
            'created_at_view' => optional($voucher->created_at)->format('d-m-Y / h:i A'),
            'items' => array_values($rows),
            'job_workers' => JobWorker::where('company_id', (int) $voucher->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn($worker) => [
                    'id' => (int) $worker->id,
                    'name' => $worker->name,
                ])
                ->values(),
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
