<?php

namespace App\Http\Controllers\Company;

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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class TreeCuttingOfficeController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $fromDate = $request->get('from_date', now()->subDays(6)->toDateString());
        $toDate = $request->get('to_date', now()->toDateString());
        $workerId = $request->get('worker_id');

        if ($request->ajax()) {
            $releasedRows = function ($query) use ($company) {
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
                ->when($workerId, fn($q) => $q->where('job_worker_id', $workerId))
                ->with(['process:id,name', 'jobWorker:id,name'])
                ->select('vacuum_vouchers.*')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_release_items')
                        ->selectRaw('MAX(COALESCE(casting_release_items.released_at, casting_release_items.created_at))')
                        ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_release_items.company_id', $company->id);
                }, 'casting_receive_datetime')
                ->selectSub(function ($query) use ($releasedRows) {
                    $query->from('casting_release_items')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id');
                    $releasedRows($query);
                }, 'released_count')
                ->selectSub(function ($query) use ($releasedRows) {
                    $query->from('casting_release_items')
                        ->selectRaw('COALESCE(SUM(casting_release_items.release_tree_wt), 0)')
                        ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id');
                    $releasedRows($query);
                }, 'tree_wt_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_office_items')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('tree_cutting_office_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_office_items.company_id', $company->id)
                        ->where('tree_cutting_office_items.office_cut_wt', '>', 0);
                }, 'office_used_count')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_office_items')
                        ->selectRaw('COALESCE(SUM(tree_cutting_office_items.office_cut_wt), 0)')
                        ->whereColumn('tree_cutting_office_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_office_items.company_id', $company->id);
                }, 'office_cut_wt_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_release_items')
                        ->leftJoin('tree_cutting_office_items', function ($join) use ($company) {
                            $join->on('tree_cutting_office_items.vacuum_voucher_item_id', '=', 'casting_release_items.vacuum_voucher_item_id')
                                ->where('tree_cutting_office_items.company_id', $company->id);
                        })
                        ->selectRaw('COALESCE(SUM(GREATEST(COALESCE(casting_release_items.release_tree_wt, 0) - COALESCE(tree_cutting_office_items.office_cut_wt, 0), 0)), 0)')
                        ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_release_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->where('casting_release_items.release_tree_wt', '>', 0)
                                ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                        });
                }, 'remaining_tree_wt_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_office_items')
                        ->selectRaw('MAX(COALESCE(tree_cutting_office_items.office_cut_at, tree_cutting_office_items.updated_at, tree_cutting_office_items.created_at))')
                        ->whereColumn('tree_cutting_office_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_office_items.company_id', $company->id);
                }, 'office_datetime')
                ->orderByDesc('casting_receive_datetime')
                ->orderByDesc('id');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('voucher_no_view', fn($row) => $row->voucher_no)
                ->addColumn('date_time_view', fn($row) => $row->office_datetime ? \Carbon\Carbon::parse($row->office_datetime)->format('d-m-Y / h:i A') : ($row->casting_receive_datetime ? \Carbon\Carbon::parse($row->casting_receive_datetime)->format('d-m-Y / h:i A') : '-'))
                ->addColumn('process_name', fn($row) => $row->process?->name ?? '-')
                ->addColumn('worker_name', fn($row) => $row->jobWorker?->name ?? '-')
                ->addColumn('office_used_view', function ($row) {
                    $used = (int) ($row->office_used_count ?? 0);
                    return '<span class="count-badge ' . ($used > 0 ? 'count-assigned' : 'count-pending') . '">' . $used . '</span>';
                })
                ->addColumn('tree_wt_view', fn($row) => number_format((float) ($row->tree_wt_total ?? 0), 3, '.', ''))
                ->addColumn('office_cut_wt_view', fn($row) => number_format((float) ($row->office_cut_wt_total ?? 0), 3, '.', ''))
                ->addColumn('remaining_tree_wt_view', fn($row) => number_format((float) ($row->remaining_tree_wt_total ?? 0), 3, '.', ''))
                ->addColumn('action', function ($row) use ($company) {
                    $id = Crypt::encryptString((string) $row->id);
                    $view = route('company.tree-cutting-office.show', [$company->slug, $id]);
                    $pdf = route('company.tree-cutting-office.pdf', [$company->slug, $id]);

                    return '<div class="d-flex gap-1">
                        <a href="' . $view . '" class="btn btn-sm btn-info">View</a>
                        <a href="' . $pdf . '" class="btn btn-sm btn-success">PDF</a>
                    </div>';
                })
                ->rawColumns(['office_used_view', 'action'])
                ->make(true);
        }

        $jobWorkers = WorkerPersonService::activeWorkers((int) $company->id);

        return view('company.tree_cutting_office.index', compact('company', 'fromDate', 'toDate', 'jobWorkers'));
    }

    public function show($slug, $encryptedId)
    {
        [$company, $voucher, $releaseItems, $officeItems] = $this->voucherData($slug, $encryptedId);

        return view('company.tree_cutting_office.show', compact('company', 'voucher', 'releaseItems', 'officeItems'));
    }

    public function pdf($slug, $encryptedId)
    {
        [$company, $voucher, $releaseItems, $officeItems] = $this->voucherData($slug, $encryptedId);

        return Pdf::loadView('company.tree_cutting_office.pdf.show', compact('company', 'voucher', 'releaseItems', 'officeItems'))
            ->setPaper('a4', 'portrait')
            ->download('tree_cutting_office_' . $voucher->voucher_no . '.pdf');
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $voucher = VacuumVoucher::where('company_id', $company->id)->findOrFail($id);

        $releaseItems = CastingReleaseItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->where('release_tree_wt', '>', 0)
                    ->orWhere('release_tree_bhuko', '>', 0);
            })
            ->get()
            ->keyBy('vacuum_voucher_item_id');

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

        DB::transaction(function () use ($company, $voucher, $releaseItems, $validItemIds, $validated) {
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

            $groupRows = collect($validated['items'] ?? [])
                ->filter(fn($row, $itemId) => in_array((int) $itemId, $validItemIds, true) && $rowKeepsGroup($row));
            $submittedGroupKeys = collect();

            if ($groupRows->count() > 1) {
                $firstRow = $groupRows->first();
                $groupKey = trim((string) (($firstRow['bulk_batch_key'] ?? '') ?: ($firstRow['issue_group_key'] ?? '')));
                if ($groupKey === '') {
                    $groupKey = (string) Str::uuid();
                }

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
                    $buchNo = optional($releaseItem->voucherItem)->buch_no ?: $itemId;
                    throw ValidationException::withMessages([
                        "items.{$itemId}.office_cut_wt" => "Office cutting wt cannot be greater than tree wt for {$buchNo}.",
                    ]);
                }

                $remainingTreeWt = round(max($treeWt - (float) ($officeCutWtValue ?? 0), 0), 3);
                $submittedGroupKey = $submittedGroupKeys->get($itemId);
                $submittedGroupKey = $submittedGroupKey ? (string) $submittedGroupKey : null;

                if ($officeCutWtValue === null || $officeCutWtValue <= 0) {
                    TreeCuttingOfficeItem::where('company_id', $company->id)
                        ->where('vacuum_voucher_item_id', $itemId)
                        ->delete();
                } else {
                    TreeCuttingOfficeItem::updateOrCreate(
                        [
                            'company_id' => $company->id,
                            'vacuum_voucher_item_id' => $itemId,
                        ],
                        [
                            'vacuum_voucher_id' => $voucher->id,
                            'tree_wt' => $treeWt,
                            'office_cut_wt' => $officeCutWtValue,
                            'remaining_tree_wt' => $remainingTreeWt,
                            'issue_group_key' => $submittedGroupKey,
                            'created_by' => auth()->id(),
                            'updated_by' => auth()->id(),
                            'office_cut_at' => now(),
                        ]
                    );
                }

                $issueItem = TreeCuttingIssueItem::where('company_id', $company->id)
                    ->where('vacuum_voucher_id', $voucher->id)
                    ->where('vacuum_voucher_item_id', $itemId)
                    ->where('is_custom', false)
                    ->first();

                if (!$issueItem) {
                    continue;
                }

                if ($remainingTreeWt <= 0) {
                    TreeCuttingReceiveItem::where('company_id', $company->id)
                        ->where('tree_cutting_issue_item_id', $issueItem->id)
                        ->delete();
                    $issueItem->delete();
                    continue;
                }

                if ((float) $issueItem->receive_tree_wt !== $remainingTreeWt) {
                    TreeCuttingReceiveItem::where('company_id', $company->id)
                        ->where(function ($query) use ($issueItem) {
                            $query->where('tree_cutting_issue_item_id', $issueItem->id);
                            if ($issueItem->issue_group_key) {
                                $query->orWhere('issue_group_key', $issueItem->issue_group_key);
                            }
                        })
                        ->delete();

                    $issueItem->update(['receive_tree_wt' => $remainingTreeWt]);
                }

                if ($issueItem->issue_group_key !== $submittedGroupKey) {
                    TreeCuttingReceiveItem::where('company_id', $company->id)
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

                    $issueItem->update(['issue_group_key' => $submittedGroupKey]);
                }
            }
        });

        return redirect()
            ->route('company.tree-cutting-office.index', $company->slug)
            ->with('success', 'Tree cutting office updated successfully');
    }

    private function voucherData($slug, $encryptedId): array
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $voucher = VacuumVoucher::where('company_id', $company->id)
            ->with(['process:id,name', 'jobWorker:id,name', 'items'])
            ->withCount('items')
            ->findOrFail($id);

        $releaseItems = CastingReleaseItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->where('release_tree_wt', '>', 0)
                    ->orWhere('release_tree_bhuko', '>', 0);
            })
            ->with('voucherItem:id,buch_no')
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        abort_if($releaseItems->isEmpty(), 404);

        $officeItems = TreeCuttingOfficeItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        return [$company, $voucher, $releaseItems, $officeItems];
    }
}
