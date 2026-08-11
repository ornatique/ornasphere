<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CastingReleaseItem;
use App\Models\Company;
use App\Models\TreeCuttingIssueItem;
use App\Models\TreeCuttingOfficeItem;
use App\Models\TreeCuttingReceiveItem;
use App\Models\VacuumVoucher;
use App\Services\WorkerPersonService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TreeCuttingIssueApiController extends Controller
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
                    ->from('casting_release_items')
                    ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_release_items.company_id', $companyId)
                    ->where(function ($q) {
                        $q->where('casting_release_items.is_custom', false)
                            ->orWhereNull('casting_release_items.is_custom');
                    })
                    ->where(function ($q) {
                        $q->where('casting_release_items.release_tree_wt', '>', 0)
                            ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
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
                    ->where('casting_release_items.company_id', $companyId)
                    ->where(function ($q) {
                        $q->where('casting_release_items.is_custom', false)
                            ->orWhereNull('casting_release_items.is_custom');
                    });
            }, 'casting_receive_datetime')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_release_items')
                    ->leftJoin('tree_cutting_office_items', function ($join) use ($companyId) {
                        $join->on('tree_cutting_office_items.vacuum_voucher_item_id', '=', 'casting_release_items.vacuum_voucher_item_id')
                            ->where('tree_cutting_office_items.company_id', $companyId);
                    })
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_release_items.company_id', $companyId)
                    ->where(function ($q) {
                        $q->where('casting_release_items.is_custom', false)
                            ->orWhereNull('casting_release_items.is_custom');
                    })
                    ->where(function ($q) {
                        $q->where('casting_release_items.release_tree_wt', '>', 0)
                            ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                    })
                    ->whereRaw('GREATEST(COALESCE(casting_release_items.release_tree_wt, 0) - COALESCE(tree_cutting_office_items.office_cut_wt, 0), 0) > 0');
            }, 'received_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_issue_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId)
                    ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0);
            }, 'tree_cutting_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_issue_items')
                    ->selectRaw('COALESCE(SUM(tree_cutting_issue_items.receive_tree_wt), 0)')
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId)
                    ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0);
            }, 'receive_tree_wt_total')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_office_items')
                    ->selectRaw('COALESCE(SUM(tree_cutting_office_items.office_cut_wt), 0)')
                    ->whereColumn('tree_cutting_office_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_office_items.company_id', $companyId);
            }, 'office_cut_wt_total')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_issue_items')
                    ->leftJoin('job_workers', 'job_workers.id', '=', 'tree_cutting_issue_items.job_worker_id')
                    ->selectRaw("GROUP_CONCAT(DISTINCT job_workers.name ORDER BY job_workers.name SEPARATOR ', ')")
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId)
                    ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0);
            }, 'tree_cutting_worker_names')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_issue_items')
                    ->selectRaw('MAX(COALESCE(tree_cutting_issue_items.issued_at, tree_cutting_issue_items.created_at))')
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId)
                    ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0);
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
                    'office_cut_wt_total' => $this->decimalValue($row->office_cut_wt_total, 3),
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

        $validItemIds = $this->eligibleReleaseItemIds($companyId, (int) $voucher->id);
        $officeGroupKeys = TreeCuttingOfficeItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->whereNotNull('issue_group_key')
            ->pluck('issue_group_key', 'vacuum_voucher_item_id');
        $issueItemIdMap = TreeCuttingIssueItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where('is_custom', false)
            ->pluck('vacuum_voucher_item_id', 'id');

        if ($validItemIds === []) {
            return response()->json([
                'success' => false,
                'message' => 'Tree Cutting Issue voucher not found',
            ], 404);
        }

        $validated = $request->validate([
            'auto_group' => ['nullable'],
            'group_item_ids' => ['nullable', 'array'],
            'group_item_ids.*' => ['integer'],
            'group_worker_id' => [
                'nullable',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'selected_worker_id' => [
                'nullable',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'items' => ['nullable', 'array'],
            'items.*.receive_tree_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.issue_group_key' => ['nullable', 'string', 'max:64'],
            'items.*.bulk_batch_key' => ['nullable', 'string', 'max:64'],
            'items.*.is_group_checked' => ['nullable'],
            'items.*.group_checked' => ['nullable'],
            'items.*.checked' => ['nullable'],
            'items.*.is_checked' => ['nullable'],
            'items.*.selected' => ['nullable'],
            'items.*.job_worker_id' => [
                'nullable',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'custom_items' => ['nullable', 'array'],
            'custom_items.*.id' => ['nullable', 'integer'],
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
            'custom_existing.*.issue_group_key' => ['nullable', 'string', 'max:64'],
            'custom_existing.*.bulk_batch_key' => ['nullable', 'string', 'max:64'],
            'custom_existing.*.keep_group' => ['nullable'],
            'custom_existing.*.group_checked' => ['nullable'],
            'custom_existing.*.selected_for_group' => ['nullable'],
            'custom_existing.*.job_worker_id' => [
                'nullable',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        DB::transaction(function () use ($request, $companyId, $voucher, $validItemIds, $validated, $officeGroupKeys, $issueItemIdMap) {
            $autoGroupRowsByWorker = [];
            $autoGroupEnabled = $request->boolean('auto_group');
            $resolveItemId = function ($itemId) use ($validItemIds, $issueItemIdMap): int {
                $itemId = (int) $itemId;

                if (in_array($itemId, $validItemIds, true)) {
                    return $itemId;
                }

                return (int) ($issueItemIdMap->get($itemId) ?? $itemId);
            };
            $groupItemIds = collect($validated['group_item_ids'] ?? [])
                ->map(fn($itemId) => $resolveItemId($itemId))
                ->filter(fn($itemId) => $itemId > 0)
                ->values();
            $hasExplicitGroupItems = $groupItemIds->isNotEmpty();
            $groupWorkerId = $validated['group_worker_id'] ?? ($validated['selected_worker_id'] ?? null);
            $hasRowGroupFlags = collect($validated['items'] ?? [])->contains(function ($row) {
                return array_key_exists('is_group_checked', $row)
                    || array_key_exists('group_checked', $row)
                    || array_key_exists('checked', $row)
                    || array_key_exists('is_checked', $row)
                    || array_key_exists('selected', $row);
            });

            foreach (($validated['items'] ?? []) as $itemId => $row) {
                $itemId = $resolveItemId($itemId);
                $rowMarkedForGroup = $hasExplicitGroupItems
                    ? $groupItemIds->contains($itemId)
                    : (
                        $hasRowGroupFlags
                            ? ($this->truthy($row['is_group_checked'] ?? false)
                                || $this->truthy($row['group_checked'] ?? false)
                                || $this->truthy($row['checked'] ?? false)
                                || $this->truthy($row['is_checked'] ?? false)
                                || $this->truthy($row['selected'] ?? false))
                            : $autoGroupEnabled
                    );
                $jobWorkerId = $row['job_worker_id'] ?? ($rowMarkedForGroup ? $groupWorkerId : null);
                $receiveTreeWt = $row['receive_tree_wt'] ?? null;
                $receiveTreeWtValue = $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null;

                if (
                    !in_array($itemId, $validItemIds, true)
                    || $officeGroupKeys->has($itemId)
                    || !$rowMarkedForGroup
                    || $jobWorkerId === null
                    || $jobWorkerId === ''
                    || $receiveTreeWtValue === null
                    || $receiveTreeWtValue <= 0
                ) {
                    continue;
                }

                $autoGroupRowsByWorker[(int) $jobWorkerId][$itemId] = $row;
            }

            $autoGroupKeys = collect();
            foreach ($autoGroupRowsByWorker as $rows) {
                if (count($rows) <= 1) {
                    continue;
                }

                $firstRow = reset($rows);
                $groupKey = trim((string) (($firstRow['bulk_batch_key'] ?? '') ?: ($firstRow['issue_group_key'] ?? '')));
                if ($groupKey === '') {
                    $groupKey = (string) Str::uuid();
                }

                foreach (array_keys($rows) as $itemId) {
                    $autoGroupKeys->put((int) $itemId, $groupKey);
                }
            }

            foreach (($validated['items'] ?? []) as $itemId => $row) {
                $itemId = $resolveItemId($itemId);

                if (!in_array($itemId, $validItemIds, true)) {
                    continue;
                }

                $receiveTreeWt = $row['receive_tree_wt'] ?? null;
                $rowMarkedForGroup = $hasExplicitGroupItems
                    ? $groupItemIds->contains($itemId)
                    : (
                        $hasRowGroupFlags
                            ? ($this->truthy($row['is_group_checked'] ?? false)
                                || $this->truthy($row['group_checked'] ?? false)
                                || $this->truthy($row['checked'] ?? false)
                                || $this->truthy($row['is_checked'] ?? false)
                                || $this->truthy($row['selected'] ?? false))
                            : $autoGroupEnabled
                    );
                $jobWorkerId = $row['job_worker_id'] ?? ($rowMarkedForGroup ? $groupWorkerId : null);
                $receiveTreeWtValue = $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null;
                $submittedGroupKey = trim((string) (($row['bulk_batch_key'] ?? '') ?: ($row['issue_group_key'] ?? '')));
                if ($officeGroupKeys->has($itemId)) {
                    $submittedGroupKey = (string) $officeGroupKeys->get($itemId);
                }
                if ($autoGroupKeys->has($itemId)) {
                    $submittedGroupKey = (string) $autoGroupKeys->get($itemId);
                }

                if ($receiveTreeWtValue === null || $receiveTreeWtValue <= 0) {
                    $treeIssueIds = TreeCuttingIssueItem::where('company_id', $companyId)
                        ->where('vacuum_voucher_item_id', $itemId)
                        ->where('is_custom', false)
                        ->pluck('id');

                    if ($treeIssueIds->isNotEmpty()) {
                        TreeCuttingReceiveItem::where('company_id', $companyId)
                            ->whereIn('tree_cutting_issue_item_id', $treeIssueIds)
                            ->delete();

                        TreeCuttingIssueItem::whereIn('id', $treeIssueIds)
                            ->delete();
                    }

                    TreeCuttingIssueItem::where('company_id', $companyId)
                        ->where('vacuum_voucher_item_id', $itemId)
                        ->where('is_custom', false)
                        ->delete();
                    continue;
                }

                $issueItem = TreeCuttingIssueItem::firstOrNew([
                    'company_id' => $companyId,
                    'vacuum_voucher_item_id' => $itemId,
                ]);
                $oldGroupKey = $issueItem->exists ? $issueItem->issue_group_key : null;
                $oldWorkerId = $issueItem->exists ? $issueItem->job_worker_id : null;
                $newWorkerId = $jobWorkerId !== null && $jobWorkerId !== '' ? (int) $jobWorkerId : null;

                if ($issueItem->exists && $oldWorkerId && $officeGroupKeys->has($itemId)) {
                    $newWorkerId = (int) $oldWorkerId;
                }

                $issueItem->fill([
                    'vacuum_voucher_id' => $voucher->id,
                    'job_worker_id' => $newWorkerId,
                    'issue_group_key' => $submittedGroupKey !== '' ? $submittedGroupKey : null,
                    'is_custom' => false,
                    'custom_buch_no' => null,
                    'receive_tree_wt' => $receiveTreeWtValue,
                    'issued_by' => (int) $request->user()->id,
                    'issued_at' => now(),
                ])->save();

                if ($oldGroupKey !== $issueItem->issue_group_key) {
                    TreeCuttingReceiveItem::where('company_id', $companyId)
                        ->where(function ($query) use ($issueItem, $oldGroupKey) {
                            $query->where('tree_cutting_issue_item_id', $issueItem->id);
                            if ($oldGroupKey) {
                                $query->orWhere('issue_group_key', $oldGroupKey);
                            }
                            if ($issueItem->issue_group_key) {
                                $query->orWhere('issue_group_key', $issueItem->issue_group_key);
                            }
                        })
                        ->delete();
                }
            }

            if ($hasExplicitGroupItems) {
                $groupedItemIdsByWorker = [];

                foreach (($validated['items'] ?? []) as $itemId => $row) {
                    $itemId = $resolveItemId($itemId);

                    if (!$groupItemIds->contains($itemId) || !in_array($itemId, $validItemIds, true) || $officeGroupKeys->has($itemId)) {
                        continue;
                    }

                    $workerId = $row['job_worker_id'] ?? $groupWorkerId;
                    if ($workerId === null || $workerId === '') {
                        continue;
                    }

                    $groupedItemIdsByWorker[(int) $workerId][] = $itemId;
                }

                foreach ($groupedItemIdsByWorker as $workerId => $itemIds) {
                    $itemIds = array_values(array_unique(array_map('intval', $itemIds)));

                    if (count($itemIds) <= 1) {
                        TreeCuttingIssueItem::where('company_id', $companyId)
                            ->where('vacuum_voucher_id', $voucher->id)
                            ->whereIn('vacuum_voucher_item_id', $itemIds)
                            ->where('is_custom', false)
                            ->update(['issue_group_key' => null]);
                        continue;
                    }

                    $groupKey = (string) Str::uuid();
                    TreeCuttingIssueItem::where('company_id', $companyId)
                        ->where('vacuum_voucher_id', $voucher->id)
                        ->where('job_worker_id', (int) $workerId)
                        ->whereIn('vacuum_voucher_item_id', $itemIds)
                        ->where('is_custom', false)
                        ->update(['issue_group_key' => $groupKey]);

                    TreeCuttingReceiveItem::where('company_id', $companyId)
                        ->whereIn('tree_cutting_issue_item_id', function ($query) use ($companyId, $voucher, $workerId, $itemIds) {
                            $query->select('id')
                                ->from('tree_cutting_issue_items')
                                ->where('company_id', $companyId)
                                ->where('vacuum_voucher_id', $voucher->id)
                                ->where('job_worker_id', (int) $workerId)
                                ->whereIn('vacuum_voucher_item_id', $itemIds)
                                ->where('is_custom', false);
                        })
                        ->delete();
                }
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

                $receiveTreeWtValue = $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null;
                $keepGroup = $request->boolean("custom_existing.$issueItemId.keep_group")
                    || $request->boolean("custom_existing.$issueItemId.group_checked")
                    || $request->boolean("custom_existing.$issueItemId.selected_for_group");
                $submittedGroupKey = trim((string) ($row['issue_group_key'] ?? $row['bulk_batch_key'] ?? ''));
                $issueGroupKey = $keepGroup && $submittedGroupKey !== '' ? $submittedGroupKey : null;

                if (($customBuchNo === '') && ($receiveTreeWtValue === null || $receiveTreeWtValue <= 0) && ($jobWorkerId === null || $jobWorkerId === '')) {
                    TreeCuttingReceiveItem::where('company_id', $companyId)
                        ->where('tree_cutting_issue_item_id', $issueItem->id)
                        ->delete();

                    $issueItem->delete();
                    continue;
                }

                $issueItem->update([
                    'job_worker_id' => $jobWorkerId !== null && $jobWorkerId !== '' ? (int) $jobWorkerId : null,
                    'custom_buch_no' => $customBuchNo,
                    'receive_tree_wt' => $receiveTreeWtValue,
                    'issue_group_key' => $issueGroupKey,
                    'issued_by' => (int) $request->user()->id,
                    'issued_at' => now(),
                ]);
            }

            foreach (($validated['custom_items'] ?? []) as $row) {
                $customItemId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
                $customBuchNo = trim((string) ($row['custom_buch_no'] ?? ''));
                $receiveTreeWt = $row['receive_tree_wt'] ?? null;
                $jobWorkerId = $row['job_worker_id'] ?? null;

                $receiveTreeWtValue = $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null;

                if ($customBuchNo === '' && ($receiveTreeWtValue === null || $receiveTreeWtValue <= 0) && ($jobWorkerId === null || $jobWorkerId === '')) {
                    if ($customItemId) {
                        $issueItem = TreeCuttingIssueItem::where('company_id', $companyId)
                            ->where('vacuum_voucher_id', $voucher->id)
                            ->where('is_custom', true)
                            ->where('id', $customItemId)
                            ->first();

                        if ($issueItem) {
                            TreeCuttingReceiveItem::where('company_id', $companyId)
                                ->where('tree_cutting_issue_item_id', $issueItem->id)
                                ->delete();

                            $issueItem->delete();
                        }
                    }
                    continue;
                }

                $issueItem = $customItemId
                    ? TreeCuttingIssueItem::where('company_id', $companyId)
                        ->where('vacuum_voucher_id', $voucher->id)
                        ->where('is_custom', true)
                        ->where('id', $customItemId)
                        ->first()
                    : null;

                $payload = [
                    'company_id' => $companyId,
                    'vacuum_voucher_id' => $voucher->id,
                    'vacuum_voucher_item_id' => null,
                    'job_worker_id' => $jobWorkerId !== null && $jobWorkerId !== '' ? (int) $jobWorkerId : null,
                    'custom_buch_no' => $customBuchNo,
                    'is_custom' => true,
                    'receive_tree_wt' => $receiveTreeWtValue,
                    'issued_by' => (int) $request->user()->id,
                    'issued_at' => now(),
                ];

                if ($issueItem) {
                    $issueItem->update($payload);
                } else {
                    TreeCuttingIssueItem::create($payload);
                }
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
                $q->where('is_custom', false)
                    ->orWhereNull('is_custom');
            })
            ->where(function ($q) {
                $q->where('release_tree_wt', '>', 0)
                    ->orWhere('release_tree_bhuko', '>', 0);
            })
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        $officeItems = TreeCuttingOfficeItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy(fn($item) => $this->treeSourceKey($item));

        $receiveItems = $receiveItems
            ->map(function ($receiveItem, $itemId) use ($officeItems) {
                $officeCutWt = (float) ($officeItems->get((string) $itemId)?->office_cut_wt ?? 0);
                $releaseTreeWt = (float) ($receiveItem->release_tree_wt ?? 0);
                $receiveItem->office_cut_wt = $officeCutWt;
                $receiveItem->office_issue_group_key = $officeItems->get((string) $itemId)?->issue_group_key;
                $receiveItem->remaining_tree_wt = round(max($releaseTreeWt - $officeCutWt, 0), 3);

                return $receiveItem;
            })
            ->filter(fn($receiveItem) => (float) ($receiveItem->remaining_tree_wt ?? 0) > 0)
            ->keyBy('vacuum_voucher_item_id');

        if ($receiveItems->isEmpty()) {
            return null;
        }

        $treeCuttingItems = TreeCuttingIssueItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where('is_custom', false)
            ->where('receive_tree_wt', '>', 0)
            ->with('jobWorker:id,name')
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        $customTreeCuttingItems = TreeCuttingIssueItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where('is_custom', true)
            ->with(['jobWorker:id,name', 'castingReleaseItem:id,release_tree_wt,release_tree_bhuko,custom_type'])
            ->orderBy('id')
            ->get();

        return [$voucher, $receiveItems, $treeCuttingItems, $customTreeCuttingItems, $officeItems];
    }

    private function formatVoucher(VacuumVoucher $voucher, $receiveItems, $treeCuttingItems, $customTreeCuttingItems, $officeItems = null): array
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
            $defaultReceiveTreeWt = $treeCuttingItem?->receive_tree_wt ?? $receiveItem?->remaining_tree_wt;
            $defaultWorkerId = $treeCuttingItem?->job_worker_id;
            $issueGroupKey = $treeCuttingItem?->issue_group_key ?: ($receiveItem?->office_issue_group_key ?? null);

            $rows[] = [
                'id' => $treeCuttingItem?->id ? (int) $treeCuttingItem->id : null,
                'vacuum_voucher_item_id' => (int) $item->id,
                'vacuum_buch_id' => $item->vacuum_buch_id ? (int) $item->vacuum_buch_id : null,
                'buch_no' => $item->buch_no,
                'is_custom' => false,
                'receive_tree_wt' => $defaultReceiveTreeWt !== null ? $this->decimalValue($defaultReceiveTreeWt, 3) : null,
                'office_cut_wt' => $this->decimalValue($receiveItem?->office_cut_wt, 3),
                'remaining_tree_wt' => $this->decimalValue($receiveItem?->remaining_tree_wt, 3),
                'source_release_tree_wt' => $receiveItem?->release_tree_wt !== null ? $this->decimalValue($receiveItem->release_tree_wt, 3) : null,
                'source_release_tree_bhuko' => $receiveItem?->release_tree_bhuko !== null ? $this->decimalValue($receiveItem->release_tree_bhuko, 3) : null,
                'issue_group_key' => $issueGroupKey,
                'is_office_group' => (bool) ($receiveItem?->office_issue_group_key),
                'job_worker_id' => $defaultWorkerId ? (int) $defaultWorkerId : null,
                'worker_name' => $treeCuttingItem?->jobWorker?->name,
                'issued_at' => optional($treeCuttingItem?->issued_at)->format('Y-m-d H:i:s'),
                'issued_at_view' => optional($treeCuttingItem?->issued_at)->format('d-m-Y / h:i A'),
            ];
        }

        foreach ($customTreeCuttingItems as $customItem) {
            $officeItem = $officeItems?->get($this->treeSourceKey($customItem));
            $sourceReleaseTreeWt = $customItem->castingReleaseItem?->release_tree_wt;
            $officeCutWt = (float) ($officeItem?->office_cut_wt ?? 0);
            $remainingTreeWt = $sourceReleaseTreeWt !== null
                ? round(max((float) $sourceReleaseTreeWt - $officeCutWt, 0), 3)
                : null;

            $rows[] = [
                'id' => (int) $customItem->id,
                'vacuum_voucher_item_id' => null,
                'casting_release_item_id' => $customItem->casting_release_item_id ? (int) $customItem->casting_release_item_id : null,
                'vacuum_buch_id' => null,
                'buch_no' => $customItem->custom_buch_no,
                'custom_buch_no' => $customItem->custom_buch_no,
                'is_custom' => true,
                'custom_type' => $customItem->castingReleaseItem?->custom_type,
                'receive_tree_wt' => $customItem->receive_tree_wt !== null ? $this->decimalValue($customItem->receive_tree_wt, 3) : null,
                'office_cut_wt' => $this->decimalValue($officeCutWt, 3),
                'remaining_tree_wt' => $remainingTreeWt !== null ? $this->decimalValue($remainingTreeWt, 3) : null,
                'source_release_tree_wt' => $sourceReleaseTreeWt !== null ? $this->decimalValue($sourceReleaseTreeWt, 3) : null,
                'source_release_tree_bhuko' => $customItem->castingReleaseItem?->release_tree_bhuko !== null ? $this->decimalValue($customItem->castingReleaseItem->release_tree_bhuko, 3) : null,
                'issue_group_key' => $customItem->issue_group_key ?: ($officeItem?->issue_group_key ?? null),
                'is_office_group' => (bool) ($officeItem?->issue_group_key),
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
            'assigned_tree_cutting' => count(array_filter($rows, fn($row) => (float) ($row['receive_tree_wt'] ?? 0) > 0)),
            'receive_tree_wt_total' => $this->decimalValue(collect($rows)->sum(fn($row) => (float) ($row['receive_tree_wt'] ?? 0)), 3),
            'office_cut_wt_total' => $this->decimalValue(collect($rows)->sum(fn($row) => (float) ($row['office_cut_wt'] ?? 0)), 3),
            'created_at' => optional($voucher->created_at)->format('Y-m-d H:i:s'),
            'created_at_view' => optional($voucher->created_at)->format('d-m-Y / h:i A'),
            'items' => array_values($rows),
            'job_workers' => WorkerPersonService::activeWorkers((int) $voucher->company_id)
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

    private function treeSourceKey($item): string
    {
        if ((bool) ($item->is_custom ?? false) && !empty($item->casting_release_item_id)) {
            return 'custom_' . $item->casting_release_item_id;
        }

        return (string) $item->vacuum_voucher_item_id;
    }

    private function truthy($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function eligibleReleaseItemIds(int $companyId, int $voucherId): array
    {
        return CastingReleaseItem::query()
            ->leftJoin('tree_cutting_office_items', function ($join) use ($companyId) {
                $join->on('tree_cutting_office_items.vacuum_voucher_item_id', '=', 'casting_release_items.vacuum_voucher_item_id')
                    ->where('tree_cutting_office_items.company_id', $companyId);
            })
            ->where('casting_release_items.company_id', $companyId)
            ->where('casting_release_items.vacuum_voucher_id', $voucherId)
            ->where(function ($q) {
                $q->where('casting_release_items.is_custom', false)
                    ->orWhereNull('casting_release_items.is_custom');
            })
            ->where(function ($q) {
                $q->where('casting_release_items.release_tree_wt', '>', 0)
                    ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
            })
            ->whereRaw('GREATEST(COALESCE(casting_release_items.release_tree_wt, 0) - COALESCE(tree_cutting_office_items.office_cut_wt, 0), 0) > 0')
            ->pluck('casting_release_items.vacuum_voucher_item_id')
            ->map(fn($itemId) => (int) $itemId)
            ->all();
    }
}
