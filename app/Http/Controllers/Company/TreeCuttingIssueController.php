<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CastingReleaseItem;
use App\Models\Company;
use App\Models\TreeCuttingIssueItem;
use App\Models\TreeCuttingReceiveItem;
use App\Models\VacuumVoucher;
use App\Services\WorkerPersonService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class TreeCuttingIssueController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $fromDate = $request->get('from_date', now()->subDays(6)->toDateString());
        $toDate = $request->get('to_date', now()->toDateString());
        $workerId = $request->get('worker_id');

        if ($request->ajax()) {
            $receivedRows = function ($query) use ($company) {
                $query->where('casting_release_items.company_id', $company->id)
                    ->where(function ($q) {
                        $q->where('casting_release_items.release_tree_wt', '>', 0)
                            ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                    });
            };

            $rows = VacuumVoucher::query()
                ->where('company_id', $company->id)
                ->whereExists(function ($query) use ($company, $fromDate, $toDate) {
                    $query->selectRaw('1')
                        ->from('casting_release_items')
                        ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_release_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->where('casting_release_items.release_tree_wt', '>', 0)
                                ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                        })
                        ->when($fromDate, fn($q) => $q->whereDate(DB::raw('COALESCE(casting_release_items.released_at, casting_release_items.created_at)'), '>=', $fromDate))
                        ->when($toDate, fn($q) => $q->whereDate(DB::raw('COALESCE(casting_release_items.released_at, casting_release_items.created_at)'), '<=', $toDate));
                })
                ->when($workerId, function ($q) use ($company, $workerId) {
                    $q->where(function ($workerQuery) use ($company, $workerId) {
                        $workerQuery->where('job_worker_id', $workerId)
                            ->orWhereExists(function ($query) use ($company, $workerId) {
                                $query->selectRaw('1')
                                    ->from('tree_cutting_issue_items')
                                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                                    ->where('tree_cutting_issue_items.company_id', $company->id)
                                    ->where('tree_cutting_issue_items.job_worker_id', $workerId);
                            });
                    });
                })
                ->with(['process:id,name', 'jobWorker:id,name'])
                ->select('vacuum_vouchers.*')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_release_items')
                        ->selectRaw('MAX(COALESCE(casting_release_items.released_at, casting_release_items.created_at))')
                        ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_release_items.company_id', $company->id);
                }, 'casting_receive_datetime')
                ->selectSub(function ($query) use ($receivedRows) {
                    $query->from('casting_release_items')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id');
                    $receivedRows($query);
                }, 'received_count')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id)
                        ->whereNotNull('tree_cutting_issue_items.job_worker_id')
                        ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0);
                }, 'tree_cutting_count')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->selectRaw('COALESCE(SUM(tree_cutting_issue_items.receive_tree_wt), 0)')
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id)
                        ->whereNotNull('tree_cutting_issue_items.job_worker_id')
                        ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0);
                }, 'receive_tree_wt_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->leftJoin('job_workers', 'job_workers.id', '=', 'tree_cutting_issue_items.job_worker_id')
                        ->selectRaw("GROUP_CONCAT(DISTINCT job_workers.name ORDER BY job_workers.name SEPARATOR ', ')")
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id)
                        ->whereNotNull('tree_cutting_issue_items.job_worker_id')
                        ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0);
                }, 'tree_cutting_worker_names')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->selectRaw('MAX(COALESCE(tree_cutting_issue_items.issued_at, tree_cutting_issue_items.created_at))')
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id)
                        ->whereNotNull('tree_cutting_issue_items.job_worker_id')
                        ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0);
                }, 'tree_cutting_issue_datetime')
                ->orderByDesc('casting_receive_datetime')
                ->orderByDesc('id');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('voucher_no_view', fn($row) => $row->voucher_no)
                ->addColumn('date_time_view', fn($row) => $row->tree_cutting_issue_datetime ? \Carbon\Carbon::parse($row->tree_cutting_issue_datetime)->format('d-m-Y / h:i A') : ($row->casting_receive_datetime ? \Carbon\Carbon::parse($row->casting_receive_datetime)->format('d-m-Y / h:i A') : '-'))
                ->addColumn('process_name', fn($row) => $row->process?->name ?? '-')
                ->addColumn('worker_name', fn($row) => $row->tree_cutting_worker_names ?: ($row->jobWorker?->name ?? '-'))
                ->addColumn('assigned_tree_cutting_view', function ($row) {
                    return '<span class="count-badge count-assigned">' . (int) ($row->tree_cutting_count ?? 0) . '</span>';
                })
                ->addColumn('receive_tree_wt_view', fn($row) => number_format((float) ($row->receive_tree_wt_total ?? 0), 3, '.', ''))
                ->addColumn('pending_tree_cutting_view', function ($row) {
                    $received = (int) ($row->received_count ?? 0);
                    $assigned = (int) ($row->tree_cutting_count ?? 0);
                    $pending = max($received - $assigned, 0);

                    return '<span class="count-badge ' . ($pending > 0 ? 'count-pending' : 'count-complete') . '">' . $pending . '</span>';
                })
                ->addColumn('action', function ($row) use ($company) {
                    $id = Crypt::encryptString((string) $row->id);
                    $view = route('company.tree-cutting-issue.show', [$company->slug, $id]);
                    $pdf = route('company.tree-cutting-issue.pdf', [$company->slug, $id]);

                    return '<div class="d-flex gap-1">
                        <a href="' . $view . '" class="btn btn-sm btn-info">View</a>
                        <a href="' . $pdf . '" class="btn btn-sm btn-success">PDF</a>
                    </div>';
                })
                ->rawColumns(['assigned_tree_cutting_view', 'pending_tree_cutting_view', 'action'])
                ->make(true);
        }

        $jobWorkers = WorkerPersonService::activeWorkers((int) $company->id);

        return view('company.tree_cutting_issue.index', compact('company', 'fromDate', 'toDate', 'jobWorkers'));
    }

    public function show($slug, $encryptedId)
    {
        [$company, $voucher, $receiveItems, $treeCuttingItems, $customTreeCuttingItems, $jobWorkers] = $this->voucherData($slug, $encryptedId);

        return view('company.tree_cutting_issue.show', compact('company', 'voucher', 'receiveItems', 'treeCuttingItems', 'customTreeCuttingItems', 'jobWorkers'));
    }

    public function pdf($slug, $encryptedId)
    {
        [$company, $voucher, $receiveItems, $treeCuttingItems, $customTreeCuttingItems] = $this->voucherData($slug, $encryptedId);

        return Pdf::loadView('company.tree_cutting_issue.pdf.show', compact('company', 'voucher', 'receiveItems', 'treeCuttingItems', 'customTreeCuttingItems'))
            ->setPaper('a4', 'portrait')
            ->download('tree_cutting_issue_' . $voucher->voucher_no . '.pdf');
    }

    public function applyGroup(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $voucher = VacuumVoucher::where('company_id', $company->id)->findOrFail($id);

        $validItemIds = CastingReleaseItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->where('release_tree_wt', '>', 0)
                    ->orWhere('release_tree_bhuko', '>', 0);
            })
            ->pluck('vacuum_voucher_item_id')
            ->map(fn($itemId) => (int) $itemId)
            ->all();

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer'],
            'worker_id' => [
                'required',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $company->id)),
            ],
            'receive_tree_wt' => ['nullable', 'array'],
            'receive_tree_wt.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $itemIds = collect($validated['item_ids'])
            ->map(fn($itemId) => (int) $itemId)
            ->filter(fn($itemId) => in_array($itemId, $validItemIds, true))
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            return response()->json(['message' => 'No valid rows selected'], 422);
        }

        $workerId = (int) $validated['worker_id'];
        $groupKey = $itemIds->count() > 1 ? (string) Str::uuid() : null;
        $receiveTreeWeights = collect($validated['receive_tree_wt'] ?? []);

        DB::transaction(function () use ($company, $voucher, $itemIds, $workerId, $groupKey, $receiveTreeWeights) {
            foreach ($itemIds as $itemId) {
                $receiveTreeWt = $receiveTreeWeights->get((string) $itemId);
                $receiveTreeWtValue = $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null;

                $issueItem = TreeCuttingIssueItem::firstOrNew([
                    'company_id' => $company->id,
                    'vacuum_voucher_item_id' => $itemId,
                ]);

                $oldGroupKey = $issueItem->exists ? $issueItem->issue_group_key : null;

                $issueItem->fill([
                    'vacuum_voucher_id' => $voucher->id,
                    'job_worker_id' => $workerId,
                    'issue_group_key' => $groupKey,
                    'is_custom' => false,
                    'custom_buch_no' => null,
                    'receive_tree_wt' => $receiveTreeWtValue,
                    'issued_by' => auth()->id(),
                    'issued_at' => now(),
                ])->save();

                TreeCuttingIssueItem::where('id', $issueItem->id)
                    ->update(['issue_group_key' => $groupKey]);
                $issueItem->issue_group_key = $groupKey;

                TreeCuttingReceiveItem::where('company_id', $company->id)
                    ->where(function ($query) use ($issueItem, $oldGroupKey, $groupKey) {
                        $query->where('tree_cutting_issue_item_id', $issueItem->id);
                        if ($oldGroupKey) {
                            $query->orWhere('issue_group_key', $oldGroupKey);
                        }
                        if ($groupKey) {
                            $query->orWhere('issue_group_key', $groupKey);
                        }
                    })
                    ->delete();
            }
        });

        return response()->json([
            'message' => 'Group applied successfully',
            'group_key' => $groupKey,
            'item_ids' => $itemIds,
            'worker_id' => $workerId,
        ]);
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $voucher = VacuumVoucher::where('company_id', $company->id)->findOrFail($id);

        $validItemIds = CastingReleaseItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->where('release_tree_wt', '>', 0)
                    ->orWhere('release_tree_bhuko', '>', 0);
            })
            ->pluck('vacuum_voucher_item_id')
            ->map(fn($itemId) => (int) $itemId)
            ->all();

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.receive_tree_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.group_checked' => ['nullable'],
            'items.*.selected_for_group' => ['nullable'],
            'items.*.keep_group' => ['nullable'],
            'items.*.issue_group_key' => ['nullable', 'string', 'max:64'],
            'items.*.bulk_batch_key' => ['nullable', 'string', 'max:64'],
            'items.*.job_worker_id' => [
                'nullable',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $company->id)),
            ],
            'group_action_worker_id' => [
                'nullable',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $company->id)),
            ],
            'group_action_item_ids' => ['nullable', 'string'],
            'custom_items' => ['nullable', 'array'],
            'custom_items.*.custom_buch_no' => ['nullable', 'string', 'max:255'],
            'custom_items.*.receive_tree_wt' => ['nullable', 'numeric', 'min:0'],
            'custom_items.*.job_worker_id' => [
                'nullable',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $company->id)),
            ],
            'custom_existing' => ['nullable', 'array'],
            'custom_existing.*.custom_buch_no' => ['nullable', 'string', 'max:255'],
            'custom_existing.*.receive_tree_wt' => ['nullable', 'numeric', 'min:0'],
            'custom_existing.*.job_worker_id' => [
                'nullable',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $company->id)),
            ],
        ]);

        DB::transaction(function () use ($company, $voucher, $validItemIds, $validated) {
            $isGroupChecked = function ($value): bool {
                if (is_array($value)) {
                    $value = end($value);
                }

                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            };
            $rowKeepsGroup = function ($row) use ($isGroupChecked): bool {
                return (array_key_exists('group_checked', $row) && $isGroupChecked($row['group_checked']))
                    || (array_key_exists('selected_for_group', $row) && $isGroupChecked($row['selected_for_group']))
                    || (array_key_exists('keep_group', $row) && $isGroupChecked($row['keep_group']));
            };

            $groupActionWorkerId = $validated['group_action_worker_id'] ?? null;
            $groupActionWorkerId = $groupActionWorkerId !== null && $groupActionWorkerId !== '' ? (int) $groupActionWorkerId : null;
            $groupActionItemIds = collect(explode(',', (string) ($validated['group_action_item_ids'] ?? '')))
                ->map(fn($itemId) => (int) trim($itemId))
                ->filter(fn($itemId) => $itemId > 0 && in_array($itemId, $validItemIds, true))
                ->unique()
                ->values();

            if ($groupActionWorkerId && $groupActionItemIds->count() > 1) {
                foreach ($groupActionItemIds as $itemId) {
                    if (isset($validated['items'][$itemId])) {
                        $validated['items'][$itemId]['job_worker_id'] = $groupActionWorkerId;
                        $validated['items'][$itemId]['group_checked'] = '1';
                        $validated['items'][$itemId]['selected_for_group'] = '1';
                    }
                }
            }

            $uncheckedGroupItemIds = collect($validated['items'] ?? [])
                ->reject(fn($row) => $rowKeepsGroup($row))
                ->keys()
                ->map(fn($itemId) => (int) $itemId)
                ->filter(fn($itemId) => in_array($itemId, $validItemIds, true))
                ->values();

            $selectedRowsByWorker = [];
            foreach (($validated['items'] ?? []) as $itemId => $row) {
                $itemId = (int) $itemId;
                $workerId = $row['job_worker_id'] ?? null;

                if (!in_array($itemId, $validItemIds, true) || !$rowKeepsGroup($row) || $workerId === null || $workerId === '') {
                    continue;
                }

                $selectedRowsByWorker[(int) $workerId][$itemId] = $row;
            }

            $submittedGroupKeys = collect();
            foreach ($selectedRowsByWorker as $rows) {
                if (count($rows) <= 1) {
                    continue;
                }

                $firstRow = reset($rows);
                $groupKey = trim((string) (($firstRow['bulk_batch_key'] ?? '') ?: ($firstRow['issue_group_key'] ?? '')));
                if ($groupKey === '') {
                    $groupKey = (string) Str::uuid();
                }

                foreach (array_keys($rows) as $itemId) {
                    $submittedGroupKeys->put((int) $itemId, $groupKey);
                }
            }

            foreach (($validated['items'] ?? []) as $itemId => $row) {
                $itemId = (int) $itemId;

                if (!in_array($itemId, $validItemIds, true)) {
                    continue;
                }

                $receiveTreeWt = $row['receive_tree_wt'] ?? null;
                $jobWorkerId = $row['job_worker_id'] ?? null;
                $rowGroupChecked = $rowKeepsGroup($row);
                $submittedGroupKey = $rowGroupChecked
                    ? trim((string) (($row['bulk_batch_key'] ?? '') ?: ($row['issue_group_key'] ?? '')))
                    : '';
                if ($rowGroupChecked && $submittedGroupKeys->has($itemId)) {
                    $submittedGroupKey = $submittedGroupKeys->get($itemId);
                }
                $receiveTreeWtValue = $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null;
                $newWorkerId = $jobWorkerId !== null && $jobWorkerId !== '' ? (int) $jobWorkerId : null;

                if ($receiveTreeWtValue === null || $receiveTreeWtValue <= 0 || !$newWorkerId) {
                    $treeIssueIds = TreeCuttingIssueItem::where('company_id', $company->id)
                        ->where('vacuum_voucher_item_id', $itemId)
                        ->where('is_custom', false)
                        ->pluck('id');

                    if ($treeIssueIds->isNotEmpty()) {
                        TreeCuttingReceiveItem::where('company_id', $company->id)
                            ->whereIn('tree_cutting_issue_item_id', $treeIssueIds)
                            ->delete();

                        TreeCuttingIssueItem::whereIn('id', $treeIssueIds)
                            ->delete();
                    }

                    TreeCuttingIssueItem::where('company_id', $company->id)
                        ->where('vacuum_voucher_item_id', $itemId)
                        ->where('is_custom', false)
                        ->delete();
                    continue;
                }

                $issueItem = TreeCuttingIssueItem::firstOrNew([
                    'company_id' => $company->id,
                    'vacuum_voucher_item_id' => $itemId,
                ]);
                $oldGroupKey = $issueItem->exists ? $issueItem->issue_group_key : null;
                $oldWorkerId = $issueItem->exists ? $issueItem->job_worker_id : null;
                $oldReceiveTreeWt = $issueItem->exists ? (float) $issueItem->receive_tree_wt : null;
                $shouldRefreshIssuedAt = !$issueItem->exists
                    || $oldGroupKey !== ($submittedGroupKey !== '' ? $submittedGroupKey : null)
                    || $oldWorkerId !== $newWorkerId
                    || $oldReceiveTreeWt !== $receiveTreeWtValue;

                $issueItem->fill([
                    'vacuum_voucher_id' => $voucher->id,
                    'job_worker_id' => $newWorkerId,
                    'issue_group_key' => $submittedGroupKey !== '' ? $submittedGroupKey : null,
                    'is_custom' => false,
                    'custom_buch_no' => null,
                    'receive_tree_wt' => $receiveTreeWtValue,
                    'issued_by' => auth()->id(),
                    'issued_at' => $shouldRefreshIssuedAt ? now() : $issueItem->issued_at,
                ])->save();

                TreeCuttingIssueItem::where('id', $issueItem->id)
                    ->update(['issue_group_key' => $submittedGroupKey !== '' ? $submittedGroupKey : null]);
                $issueItem->issue_group_key = $submittedGroupKey !== '' ? $submittedGroupKey : null;

                if ($oldGroupKey !== $issueItem->issue_group_key || $oldWorkerId !== $newWorkerId || $oldReceiveTreeWt !== $receiveTreeWtValue) {
                    TreeCuttingReceiveItem::where('company_id', $company->id)
                        ->where(function ($query) use ($issueItem, $oldGroupKey) {
                            $query->where('tree_cutting_issue_item_id', $issueItem->id);
                            if ($oldGroupKey && $issueItem->issue_group_key) {
                                $query->orWhere('issue_group_key', $oldGroupKey);
                            }
                            if ($issueItem->issue_group_key) {
                                $query->orWhere('issue_group_key', $issueItem->issue_group_key);
                            }
                        })
                        ->delete();
                }
            }

            $submittedGroupKeys
                ->filter()
                ->groupBy(fn($groupKey) => $groupKey)
                ->each(function ($groupItemKeys, $groupKey) use ($company, $voucher) {
                    $itemIds = $groupItemKeys
                        ->keys()
                        ->map(fn($itemId) => (int) $itemId)
                        ->filter()
                        ->values();

                    if ($itemIds->count() <= 1) {
                        return;
                    }

                    $issueItemIds = TreeCuttingIssueItem::where('company_id', $company->id)
                        ->where('vacuum_voucher_id', $voucher->id)
                        ->whereIn('vacuum_voucher_item_id', $itemIds)
                        ->pluck('id');

                    if ($issueItemIds->isEmpty()) {
                        return;
                    }

                    TreeCuttingReceiveItem::where('company_id', $company->id)
                        ->where(function ($query) use ($issueItemIds, $groupKey) {
                            $query->whereIn('tree_cutting_issue_item_id', $issueItemIds)
                                ->orWhere('issue_group_key', $groupKey);
                        })
                        ->delete();

                    TreeCuttingIssueItem::where('company_id', $company->id)
                        ->where('vacuum_voucher_id', $voucher->id)
                        ->whereIn('id', $issueItemIds)
                        ->update(['issue_group_key' => $groupKey]);
                });

            if ($uncheckedGroupItemIds->isNotEmpty()) {
                $ungroupedIssueItems = TreeCuttingIssueItem::where('company_id', $company->id)
                    ->where('vacuum_voucher_id', $voucher->id)
                    ->where('is_custom', false)
                    ->whereNotNull('issue_group_key')
                    ->whereIn('vacuum_voucher_item_id', $uncheckedGroupItemIds)
                    ->get(['id', 'issue_group_key']);

                if ($ungroupedIssueItems->isNotEmpty()) {
                    $ungroupedIssueItemIds = $ungroupedIssueItems->pluck('id');
                    TreeCuttingReceiveItem::where('company_id', $company->id)
                        ->whereIn('tree_cutting_issue_item_id', $ungroupedIssueItemIds)
                        ->delete();

                    TreeCuttingIssueItem::whereIn('id', $ungroupedIssueItemIds)
                        ->update(['issue_group_key' => null]);
                }
            }

            foreach (($validated['custom_existing'] ?? []) as $issueItemId => $row) {
                $issueItem = TreeCuttingIssueItem::where('company_id', $company->id)
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

                if (($customBuchNo === '') && ($receiveTreeWtValue === null || $receiveTreeWtValue <= 0) && ($jobWorkerId === null || $jobWorkerId === '')) {
                    TreeCuttingReceiveItem::where('company_id', $company->id)
                        ->where('tree_cutting_issue_item_id', $issueItem->id)
                        ->delete();

                    $issueItem->delete();
                    continue;
                }

                $issueItem->update([
                    'job_worker_id' => $jobWorkerId !== null && $jobWorkerId !== '' ? (int) $jobWorkerId : null,
                    'custom_buch_no' => $customBuchNo,
                    'receive_tree_wt' => $receiveTreeWtValue,
                    'issued_by' => auth()->id(),
                    'issued_at' => now(),
                ]);
            }

            foreach (($validated['custom_items'] ?? []) as $row) {
                $customBuchNo = trim((string) ($row['custom_buch_no'] ?? ''));
                $receiveTreeWt = $row['receive_tree_wt'] ?? null;
                $jobWorkerId = $row['job_worker_id'] ?? null;
                $receiveTreeWtValue = $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null;

                if ($customBuchNo === '' && ($receiveTreeWtValue === null || $receiveTreeWtValue <= 0) && ($jobWorkerId === null || $jobWorkerId === '')) {
                    continue;
                }

                TreeCuttingIssueItem::create([
                    'company_id' => $company->id,
                    'vacuum_voucher_id' => $voucher->id,
                    'vacuum_voucher_item_id' => null,
                    'job_worker_id' => $jobWorkerId !== null && $jobWorkerId !== '' ? (int) $jobWorkerId : null,
                    'custom_buch_no' => $customBuchNo,
                    'is_custom' => true,
                    'receive_tree_wt' => $receiveTreeWtValue,
                    'issued_by' => auth()->id(),
                    'issued_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('company.tree-cutting-issue.index', $company->slug)
            ->with('success', 'Tree cutting issue updated successfully');
    }

    private function voucherData($slug, $encryptedId): array
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $voucher = VacuumVoucher::where('company_id', $company->id)
            ->with(['process:id,name', 'jobWorker:id,name', 'items'])
            ->withCount('items')
            ->findOrFail($id);

        $receiveItems = CastingReleaseItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->where('release_tree_wt', '>', 0)
                    ->orWhere('release_tree_bhuko', '>', 0);
            })
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        abort_if($receiveItems->isEmpty(), 404);

        $treeCuttingItems = TreeCuttingIssueItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where('is_custom', false)
            ->whereNotNull('job_worker_id')
            ->where('receive_tree_wt', '>', 0)
            ->with('jobWorker:id,name')
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        $customTreeCuttingItems = TreeCuttingIssueItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where('is_custom', true)
            ->with('jobWorker:id,name')
            ->orderBy('id')
            ->get();

        $jobWorkers = WorkerPersonService::activeWorkers((int) $company->id);

        return [$company, $voucher, $receiveItems, $treeCuttingItems, $customTreeCuttingItems, $jobWorkers];
    }
}
