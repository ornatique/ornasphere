<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CastingReleaseItem;
use App\Models\Company;
use App\Models\TreeCuttingIssueItem;
use App\Models\TreeCuttingOfficeItem;
use App\Models\TreeCuttingReceiveItem;
use App\Models\VacuumVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TreeCuttingOfficeApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $fromDate = $request->input('from_date', now()->subDays(6)->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());

        $rows = VacuumVoucher::query()
            ->where('company_id', $companyId)
            ->whereExists(function ($query) use ($companyId, $fromDate, $toDate) {
                $query->selectRaw('1')
                    ->from('casting_release_items')
                    ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_release_items.company_id', $companyId)
                    ->where(function ($q) {
                        $q->where('casting_release_items.release_tree_wt', '>', 0)
                            ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                    })
                    ->when($fromDate, fn($q) => $q->whereDate(DB::raw('COALESCE(casting_release_items.released_at, casting_release_items.created_at)'), '>=', $fromDate))
                    ->when($toDate, fn($q) => $q->whereDate(DB::raw('COALESCE(casting_release_items.released_at, casting_release_items.created_at)'), '<=', $toDate));
            })
            ->when($request->filled('worker_id'), fn($q) => $q->where('job_worker_id', (int) $request->input('worker_id')))
            ->when($request->filled('process_id'), fn($q) => $q->where('vacuum_process_id', (int) $request->input('process_id')))
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
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_release_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_release_items.company_id', $companyId)
                    ->where(function ($q) {
                        $q->where('casting_release_items.release_tree_wt', '>', 0)
                            ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                    });
            }, 'released_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_release_items')
                    ->selectRaw('COALESCE(SUM(casting_release_items.release_tree_wt), 0)')
                    ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_release_items.company_id', $companyId)
                    ->where(function ($q) {
                        $q->where('casting_release_items.release_tree_wt', '>', 0)
                            ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                    });
            }, 'tree_wt_total')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_office_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('tree_cutting_office_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_office_items.company_id', $companyId)
                    ->where('tree_cutting_office_items.office_cut_wt', '>', 0);
            }, 'office_used_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_office_items')
                    ->selectRaw('COALESCE(SUM(tree_cutting_office_items.office_cut_wt), 0)')
                    ->whereColumn('tree_cutting_office_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_office_items.company_id', $companyId);
            }, 'office_cut_wt_total')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_release_items')
                    ->leftJoin('tree_cutting_office_items', function ($join) use ($companyId) {
                        $join->on('tree_cutting_office_items.vacuum_voucher_item_id', '=', 'casting_release_items.vacuum_voucher_item_id')
                            ->where('tree_cutting_office_items.company_id', $companyId);
                    })
                    ->selectRaw('COALESCE(SUM(GREATEST(COALESCE(casting_release_items.release_tree_wt, 0) - COALESCE(tree_cutting_office_items.office_cut_wt, 0), 0)), 0)')
                    ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_release_items.company_id', $companyId)
                    ->where(function ($q) {
                        $q->where('casting_release_items.release_tree_wt', '>', 0)
                            ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                    });
            }, 'remaining_tree_wt_total')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_office_items')
                    ->selectRaw('MAX(COALESCE(tree_cutting_office_items.office_cut_at, tree_cutting_office_items.updated_at, tree_cutting_office_items.created_at))')
                    ->whereColumn('tree_cutting_office_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_office_items.company_id', $companyId);
            }, 'office_datetime')
            ->orderByDesc('casting_receive_datetime')
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                $dateTime = $row->office_datetime ?: $row->casting_receive_datetime;

                return [
                    'id' => (int) $row->id,
                    'voucher_no' => $row->voucher_no,
                    'voucher_date' => optional($row->voucher_date)->format('Y-m-d'),
                    'date_time' => $dateTime ? \Carbon\Carbon::parse($dateTime)->format('d-m-Y / h:i A') : null,
                    'process_datetime' => $dateTime ? \Carbon\Carbon::parse($dateTime)->format('Y-m-d H:i:s') : null,
                    'process_id' => (int) $row->vacuum_process_id,
                    'process_name' => $row->process?->name,
                    'worker_id' => (int) $row->job_worker_id,
                    'worker_name' => $row->jobWorker?->name,
                    'released_count' => (int) ($row->released_count ?? 0),
                    'office_used' => (int) ($row->office_used_count ?? 0),
                    'tree_wt_total' => $this->decimal($row->tree_wt_total),
                    'office_cut_wt_total' => $this->decimal($row->office_cut_wt_total),
                    'remaining_tree_wt_total' => $this->decimal($row->remaining_tree_wt_total),
                ];
            })
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function show(Request $request, int $id)
    {
        $data = $this->voucherData($request, $id);

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Tree Cutting Office voucher not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatVoucher(...$data)]);
    }

    public function update(Request $request, int $id)
    {
        $companyId = (int) $request->user()->company_id;
        $voucher = VacuumVoucher::where('company_id', $companyId)->find($id);

        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Tree Cutting Office voucher not found'], 404);
        }

        $releaseItems = CastingReleaseItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->where('release_tree_wt', '>', 0)
                    ->orWhere('release_tree_bhuko', '>', 0);
            })
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        if ($releaseItems->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Tree Cutting Office voucher not found'], 404);
        }

        $validItemIds = $releaseItems->keys()->map(fn($itemId) => (int) $itemId)->all();

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.office_cut_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.group_checked' => ['nullable'],
            'items.*.selected_for_group' => ['nullable'],
            'items.*.keep_group' => ['nullable'],
            'items.*.issue_group_key' => ['nullable', 'string', 'max:64'],
            'items.*.bulk_batch_key' => ['nullable', 'string', 'max:64'],
        ]);

        DB::transaction(function () use ($request, $companyId, $voucher, $releaseItems, $validItemIds, $validated) {
            $rowKeepsGroup = fn($row) => $this->truthy($row['group_checked'] ?? false)
                || $this->truthy($row['selected_for_group'] ?? false)
                || $this->truthy($row['keep_group'] ?? false);

            $groupRows = collect($validated['items'] ?? [])
                ->filter(fn($row, $itemId) => in_array((int) $itemId, $validItemIds, true) && $rowKeepsGroup($row));
            $submittedGroupKeys = collect();

            if ($groupRows->count() > 1) {
                $firstRow = $groupRows->first();
                $groupKey = trim((string) (($firstRow['bulk_batch_key'] ?? '') ?: ($firstRow['issue_group_key'] ?? ''))) ?: (string) Str::uuid();
                foreach ($groupRows->keys() as $itemId) {
                    $submittedGroupKeys->put((int) $itemId, $groupKey);
                }
            }

            foreach (($validated['items'] ?? []) as $itemId => $row) {
                $itemId = (int) $itemId;
                if (!in_array($itemId, $validItemIds, true)) {
                    continue;
                }

                $releaseItem = $releaseItems->get($itemId);
                $treeWt = (float) ($releaseItem->release_tree_wt ?? 0);
                $officeCutWt = $row['office_cut_wt'] ?? null;
                $officeCutWtValue = $officeCutWt !== null && $officeCutWt !== '' ? round((float) $officeCutWt, 3) : null;

                if ($officeCutWtValue !== null && $officeCutWtValue > $treeWt) {
                    throw ValidationException::withMessages([
                        "items.{$itemId}.office_cut_wt" => 'Office cutting wt cannot be greater than tree wt.',
                    ]);
                }

                $remainingTreeWt = round(max($treeWt - (float) ($officeCutWtValue ?? 0), 0), 3);
                $submittedGroupKey = $submittedGroupKeys->get($itemId);
                $submittedGroupKey = $submittedGroupKey ? (string) $submittedGroupKey : null;

                if ($officeCutWtValue === null || $officeCutWtValue <= 0) {
                    TreeCuttingOfficeItem::where('company_id', $companyId)
                        ->where('vacuum_voucher_item_id', $itemId)
                        ->delete();
                } else {
                    TreeCuttingOfficeItem::updateOrCreate(
                        ['company_id' => $companyId, 'vacuum_voucher_item_id' => $itemId],
                        [
                            'vacuum_voucher_id' => $voucher->id,
                            'tree_wt' => $treeWt,
                            'office_cut_wt' => $officeCutWtValue,
                            'remaining_tree_wt' => $remainingTreeWt,
                            'issue_group_key' => $submittedGroupKey,
                            'created_by' => (int) $request->user()->id,
                            'updated_by' => (int) $request->user()->id,
                            'office_cut_at' => now(),
                        ]
                    );
                }

                $issueItem = TreeCuttingIssueItem::where('company_id', $companyId)
                    ->where('vacuum_voucher_id', $voucher->id)
                    ->where('vacuum_voucher_item_id', $itemId)
                    ->where('is_custom', false)
                    ->first();

                if (!$issueItem) {
                    continue;
                }

                if ($remainingTreeWt <= 0) {
                    TreeCuttingReceiveItem::where('company_id', $companyId)
                        ->where('tree_cutting_issue_item_id', $issueItem->id)
                        ->delete();
                    $issueItem->delete();
                    continue;
                }

                if ((float) $issueItem->receive_tree_wt !== $remainingTreeWt || $issueItem->issue_group_key !== $submittedGroupKey) {
                    TreeCuttingReceiveItem::where('company_id', $companyId)
                        ->where(function ($query) use ($issueItem, $submittedGroupKey) {
                            $query->where('tree_cutting_issue_item_id', $issueItem->id);
                            if ($issueItem->issue_group_key) {
                                $query->orWhere('issue_group_key', $issueItem->issue_group_key);
                            }
                            if ($submittedGroupKey) {
                                $query->orWhere('issue_group_key', $submittedGroupKey);
                            }
                        })
                        ->delete();

                    $issueItem->update([
                        'receive_tree_wt' => $remainingTreeWt,
                        'issue_group_key' => $submittedGroupKey,
                    ]);
                }
            }
        });

        $data = $this->voucherData($request, $voucher->id);

        return response()->json([
            'success' => true,
            'message' => 'Tree Cutting Office updated successfully',
            'data' => $data ? $this->formatVoucher(...$data) : null,
        ]);
    }

    public function pdf(Request $request, int $id)
    {
        $data = $this->voucherData($request, $id);

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Tree Cutting Office voucher not found'], 404);
        }

        [$voucher, $releaseItems, $officeItems] = $data;
        $company = Company::findOrFail((int) $request->user()->company_id);

        return Pdf::loadView('company.tree_cutting_office.pdf.show', compact('company', 'voucher', 'releaseItems', 'officeItems'))
            ->setPaper('a4', 'portrait')
            ->download('tree_cutting_office_' . $voucher->voucher_no . '.pdf');
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

        $releaseItems = CastingReleaseItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->where('release_tree_wt', '>', 0)
                    ->orWhere('release_tree_bhuko', '>', 0);
            })
            ->with('voucherItem:id,buch_no')
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        if ($releaseItems->isEmpty()) {
            return null;
        }

        $officeItems = TreeCuttingOfficeItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        return [$voucher, $releaseItems, $officeItems];
    }

    private function formatVoucher(VacuumVoucher $voucher, $releaseItems, $officeItems): array
    {
        $rows = $voucher->items->map(function ($item) use ($releaseItems, $officeItems) {
            $releaseItem = $releaseItems->get($item->id);
            if (!$releaseItem) {
                return null;
            }

            $officeItem = $officeItems->get($item->id);
            $treeWt = (float) ($releaseItem->release_tree_wt ?? 0);
            $officeCutWt = (float) ($officeItem?->office_cut_wt ?? 0);

            return [
                'id' => $officeItem?->id ? (int) $officeItem->id : null,
                'vacuum_voucher_item_id' => (int) $item->id,
                'vacuum_buch_id' => $item->vacuum_buch_id ? (int) $item->vacuum_buch_id : null,
                'buch_no' => $item->buch_no,
                'tree_wt' => $this->decimal($treeWt),
                'tree_bhuko' => $officeItem?->office_cut_wt !== null ? $this->decimal($officeCutWt) : null,
                'office_cut_wt' => $officeItem?->office_cut_wt !== null ? $this->decimal($officeCutWt) : null,
                'remaining_tree_wt' => $this->decimal(max($treeWt - $officeCutWt, 0)),
                'issue_group_key' => $officeItem?->issue_group_key,
                'office_cut_at' => optional($officeItem?->office_cut_at)->format('Y-m-d H:i:s'),
                'office_cut_at_view' => optional($officeItem?->office_cut_at)->format('d-m-Y / h:i A'),
            ];
        })->filter()->values();

        return [
            'id' => (int) $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'voucher_date' => optional($voucher->voucher_date)->format('Y-m-d'),
            'process_id' => (int) $voucher->vacuum_process_id,
            'process_name' => $voucher->process?->name,
            'worker_id' => (int) $voucher->job_worker_id,
            'worker_name' => $voucher->jobWorker?->name,
            'total_pcs' => (int) ($voucher->items_count ?? $voucher->items->count()),
            'office_used' => $rows->filter(fn($row) => (float) ($row['office_cut_wt'] ?? 0) > 0)->count(),
            'tree_wt_total' => $this->decimal($rows->sum(fn($row) => (float) $row['tree_wt'])),
            'office_cut_wt_total' => $this->decimal($rows->sum(fn($row) => (float) ($row['office_cut_wt'] ?? 0))),
            'remaining_tree_wt_total' => $this->decimal($rows->sum(fn($row) => (float) $row['remaining_tree_wt'])),
            'created_at' => optional($voucher->created_at)->format('Y-m-d H:i:s'),
            'created_at_view' => optional($voucher->created_at)->format('d-m-Y / h:i A'),
            'items' => $rows,
        ];
    }

    private function decimal($value, int $precision = 3): string
    {
        return number_format((float) ($value ?? 0), $precision, '.', '');
    }

    private function truthy($value): bool
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
