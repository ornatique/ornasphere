<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CastingMetalIssueItem;
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
use Yajra\DataTables\Facades\DataTables;

class CastingReleaseController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $fromDate = $request->get('from_date', now()->subDays(6)->toDateString());
        $toDate = $request->get('to_date', now()->toDateString());
        $workerId = $request->get('worker_id');

        if ($request->ajax()) {
            $rows = VacuumVoucher::query()
                ->where('company_id', $company->id)
                ->whereExists(function ($query) use ($company, $fromDate, $toDate) {
                    $query->selectRaw('1')
                        ->from('casting_metal_issue_items')
                        ->whereColumn('casting_metal_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_metal_issue_items.company_id', $company->id)
                        ->when($fromDate, fn($q) => $q->whereDate(DB::raw('COALESCE(casting_metal_issue_items.issued_at, casting_metal_issue_items.created_at)'), '>=', $fromDate))
                        ->when($toDate, fn($q) => $q->whereDate(DB::raw('COALESCE(casting_metal_issue_items.issued_at, casting_metal_issue_items.created_at)'), '<=', $toDate));
                })
                ->when($workerId, fn($q) => $q->where('job_worker_id', $workerId))
                ->with(['process:id,name', 'jobWorker:id,name'])
                ->select('vacuum_vouchers.*')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_metal_issue_items')
                        ->selectRaw('MAX(COALESCE(casting_metal_issue_items.issued_at, casting_metal_issue_items.created_at))')
                        ->whereColumn('casting_metal_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_metal_issue_items.company_id', $company->id);
                }, 'metal_issue_datetime')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_metal_issue_items')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('casting_metal_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_metal_issue_items.company_id', $company->id);
                }, 'metal_issue_count')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_release_items')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_release_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->where('casting_release_items.is_custom', false)
                                ->orWhereNull('casting_release_items.is_custom');
                        })
                        ->where(function ($q) {
                            $q->where('casting_release_items.release_tree_wt', '>', 0)
                                ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                        });
                }, 'assigned_receive_count')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_release_items')
                        ->selectRaw('COALESCE(SUM(casting_release_items.release_tree_wt), 0)')
                        ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_release_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->where('casting_release_items.release_tree_wt', '>', 0)
                                ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                        });
                }, 'release_tree_wt_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_release_items')
                        ->selectRaw('COALESCE(SUM(casting_release_items.release_tree_bhuko), 0)')
                        ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_release_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->where('casting_release_items.release_tree_wt', '>', 0)
                                ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                        });
                }, 'release_tree_bhuko_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_release_items')
                        ->selectRaw('COALESCE(SUM(casting_release_items.loss), 0)')
                        ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_release_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->where('casting_release_items.release_tree_wt', '>', 0)
                                ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                        });
                }, 'loss_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_release_items')
                        ->selectRaw('MAX(COALESCE(casting_release_items.released_at, casting_release_items.created_at))')
                        ->whereColumn('casting_release_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_release_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->where('casting_release_items.release_tree_wt', '>', 0)
                                ->orWhere('casting_release_items.release_tree_bhuko', '>', 0);
                        });
                }, 'release_datetime')
                ->orderByDesc('metal_issue_datetime')
                ->orderByDesc('id');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('voucher_no_view', fn($row) => $row->voucher_no)
                ->addColumn('process_name', fn($row) => $row->process?->name ?? '-')
                ->addColumn('worker_name', fn($row) => $row->jobWorker?->name ?? '-')
                ->addColumn('date_time_view', fn($row) => $row->release_datetime ? \Carbon\Carbon::parse($row->release_datetime)->format('d-m-Y / h:i A') : ($row->metal_issue_datetime ? \Carbon\Carbon::parse($row->metal_issue_datetime)->format('d-m-Y / h:i A') : '-'))
                ->addColumn('release_tree_wt_view', fn($row) => number_format((float) ($row->release_tree_wt_total ?? 0), 3, '.', ''))
                ->addColumn('release_tree_bhuko_view', fn($row) => number_format((float) ($row->release_tree_bhuko_total ?? 0), 3, '.', ''))
                ->addColumn('loss_view', fn($row) => number_format((float) ($row->loss_total ?? 0), 3, '.', ''))
                ->addColumn('assigned_receive_view', function ($row) {
                    $assigned = (int) ($row->assigned_receive_count ?? 0);

                    return '<span class="count-badge count-assigned">' . $assigned . '</span>';
                })
                ->addColumn('pending_receive_view', function ($row) {
                    $total = (int) ($row->metal_issue_count ?? 0);
                    $assigned = (int) ($row->assigned_receive_count ?? 0);
                    $pending = max($total - $assigned, 0);

                    return '<span class="count-badge ' . ($pending > 0 ? 'count-pending' : 'count-complete') . '">' . $pending . '</span>';
                })
                ->addColumn('action', function ($row) use ($company) {
                    $id = Crypt::encryptString((string) $row->id);
                    $view = route('company.casting-release.show', [$company->slug, $id]);
                    $pdf = route('company.casting-release.pdf', [$company->slug, $id]);

                    return '<div class="d-flex gap-1">
                        <a href="' . $view . '" class="btn btn-sm btn-info">View</a>
                        <a href="' . $pdf . '" class="btn btn-sm btn-success">PDF</a>
                    </div>';
                })
                ->rawColumns(['assigned_receive_view', 'pending_receive_view', 'action'])
                ->make(true);
        }

        $jobWorkers = WorkerPersonService::activeWorkers((int) $company->id);

        return view('company.casting_release.index', compact('company', 'fromDate', 'toDate', 'jobWorkers'));
    }

    public function show($slug, $encryptedId)
    {
        [$company, $voucher, $issueItems, $releaseItems, $customReleaseItems] = $this->voucherData($slug, $encryptedId);

        return view('company.casting_release.show', compact('company', 'voucher', 'issueItems', 'releaseItems', 'customReleaseItems'));
    }

    public function pdf($slug, $encryptedId)
    {
        [$company, $voucher, $issueItems, $releaseItems, $customReleaseItems] = $this->voucherData($slug, $encryptedId);

        return Pdf::loadView('company.casting_release.pdf.show', compact('company', 'voucher', 'issueItems', 'releaseItems', 'customReleaseItems'))
            ->setPaper('a4', 'portrait')
            ->download('casting_receive_' . $voucher->voucher_no . '.pdf');
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $voucher = VacuumVoucher::where('company_id', $company->id)
            ->with('items:id,vacuum_voucher_id')
            ->findOrFail($id);

        $validItemIds = $voucher->items->pluck('id')->map(fn($itemId) => (int) $itemId)->all();
        $issueItems = CastingMetalIssueItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.release_tree_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.release_tree_bhuko' => ['nullable', 'numeric', 'min:0'],
            'custom_items' => ['nullable', 'array'],
            'custom_items.*.id' => ['nullable', 'integer'],
            'custom_items.*.custom_type' => ['nullable', 'in:bhuko,pc_weight'],
            'custom_items.*.custom_buch_no' => ['nullable', 'string', 'max:100'],
            'custom_items.*.release_tree_wt' => ['nullable', 'numeric', 'min:0'],
            'custom_items.*.release_tree_bhuko' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($company, $voucher, $validItemIds, $issueItems, $validated) {
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

                if (($releaseTreeWtValue === null || $releaseTreeWtValue <= 0) && ($releaseTreeBhukoValue === null || $releaseTreeBhukoValue <= 0)) {
                    $treeIssueIds = TreeCuttingIssueItem::where('company_id', $company->id)
                        ->where('vacuum_voucher_item_id', $itemId)
                        ->pluck('id');

                    if ($treeIssueIds->isNotEmpty()) {
                        TreeCuttingReceiveItem::where('company_id', $company->id)
                            ->whereIn('tree_cutting_issue_item_id', $treeIssueIds)
                            ->delete();

                        TreeCuttingIssueItem::whereIn('id', $treeIssueIds)->delete();
                    }

                    CastingReleaseItem::where('company_id', $company->id)
                        ->where('vacuum_voucher_item_id', $itemId)
                        ->delete();

                    TreeCuttingOfficeItem::where('company_id', $company->id)
                        ->where('vacuum_voucher_item_id', $itemId)
                        ->delete();
                    continue;
                }

                $loss = $releaseTreeWtValue !== null || $releaseTreeBhukoValue !== null
                    ? round(($releaseTreeWtValue ?? 0) + ($releaseTreeBhukoValue ?? 0) - $issueSilverWt, 3)
                    : null;

                CastingReleaseItem::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'vacuum_voucher_item_id' => $itemId,
                    ],
                    [
                        'vacuum_voucher_id' => $voucher->id,
                        'custom_buch_no' => null,
                        'is_custom' => false,
                        'release_tree_wt' => $releaseTreeWtValue,
                        'release_tree_bhuko' => $releaseTreeBhukoValue,
                        'loss' => $loss,
                        'released_by' => auth()->id(),
                        'released_at' => now(),
                    ]
                );

                $officeItem = TreeCuttingOfficeItem::where('company_id', $company->id)
                    ->where('vacuum_voucher_item_id', $itemId)
                    ->first();
                $officeCutWt = (float) ($officeItem?->office_cut_wt ?? 0);
                $remainingTreeWt = round(max((float) ($releaseTreeWtValue ?? 0) - $officeCutWt, 0), 3);

                if ($officeItem) {
                    $officeItem->update([
                        'tree_wt' => $releaseTreeWtValue,
                        'remaining_tree_wt' => $remainingTreeWt,
                        'updated_by' => auth()->id(),
                    ]);
                }

                TreeCuttingIssueItem::where('company_id', $company->id)
                    ->where('vacuum_voucher_id', $voucher->id)
                    ->where('vacuum_voucher_item_id', $itemId)
                    ->where('is_custom', false)
                    ->update([
                        'receive_tree_wt' => $remainingTreeWt,
                    ]);
            }

            $submittedCustomIds = [];
            foreach (($validated['custom_items'] ?? []) as $row) {
                $customId = (int) ($row['id'] ?? 0);
                $customBuchNo = trim((string) ($row['custom_buch_no'] ?? ''));
                $customType = $this->normalizeCustomType($row['custom_type'] ?? null, $row);
                $releaseTreeWt = $row['release_tree_wt'] ?? null;
                $releaseTreeBhuko = $row['release_tree_bhuko'] ?? null;
                $releaseTreeWtValue = $releaseTreeWt !== null && $releaseTreeWt !== '' ? (float) $releaseTreeWt : null;
                $releaseTreeBhukoValue = $releaseTreeBhuko !== null && $releaseTreeBhuko !== '' ? (float) $releaseTreeBhuko : null;
                if ($customType === 'pc_weight') {
                    $releaseTreeBhukoValue = null;
                } else {
                    $releaseTreeWtValue = null;
                }

                if (
                    $customBuchNo === ''
                    && ($releaseTreeWtValue === null || $releaseTreeWtValue <= 0)
                    && ($releaseTreeBhukoValue === null || $releaseTreeBhukoValue <= 0)
                ) {
                    if ($customId > 0) {
                        CastingReleaseItem::where('company_id', $company->id)
                            ->where('vacuum_voucher_id', $voucher->id)
                            ->where('is_custom', true)
                            ->where('id', $customId)
                            ->delete();
                        $this->deleteCustomTreeFlow($company->id, $customId);
                    }
                    continue;
                }

                if (
                    $customBuchNo === ''
                    || (($releaseTreeWtValue === null || $releaseTreeWtValue <= 0) && ($releaseTreeBhukoValue === null || $releaseTreeBhukoValue <= 0))
                ) {
                    continue;
                }

                $payload = [
                    'company_id' => $company->id,
                    'vacuum_voucher_id' => $voucher->id,
                    'vacuum_voucher_item_id' => null,
                    'custom_buch_no' => $customBuchNo,
                    'is_custom' => true,
                    'custom_type' => $customType,
                    'release_tree_wt' => $releaseTreeWtValue,
                    'release_tree_bhuko' => $releaseTreeBhukoValue,
                    'loss' => round(($releaseTreeWtValue ?? 0) + ($releaseTreeBhukoValue ?? 0), 3),
                    'released_by' => auth()->id(),
                    'released_at' => now(),
                ];

                if ($customId > 0) {
                    $customItem = CastingReleaseItem::where('company_id', $company->id)
                        ->where('vacuum_voucher_id', $voucher->id)
                        ->where('is_custom', true)
                        ->where('id', $customId)
                        ->first();

                    if ($customItem) {
                        $customItem->update($payload);
                        $this->syncCustomTreeFlow($company->id, $voucher, $customItem);
                        $submittedCustomIds[] = $customItem->id;
                        continue;
                    }
                }

                $customItem = CastingReleaseItem::create($payload);
                $this->syncCustomTreeFlow($company->id, $voucher, $customItem);
                $submittedCustomIds[] = $customItem->id;
            }

            $deletedCustomIds = CastingReleaseItem::where('company_id', $company->id)
                ->where('vacuum_voucher_id', $voucher->id)
                ->where('is_custom', true)
                ->when(!empty($submittedCustomIds), fn($query) => $query->whereNotIn('id', $submittedCustomIds))
                ->pluck('id')
                ->all();

            if (!empty($deletedCustomIds)) {
                foreach ($deletedCustomIds as $deletedCustomId) {
                    $this->deleteCustomTreeFlow($company->id, (int) $deletedCustomId);
                }

                CastingReleaseItem::where('company_id', $company->id)
                    ->whereIn('id', $deletedCustomIds)
                    ->delete();
            }
        });

        return redirect()
            ->route('company.casting-release.index', $company->slug)
            ->with('success', 'Casting receive updated successfully');
    }

    private function voucherData($slug, $encryptedId): array
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $voucher = VacuumVoucher::where('company_id', $company->id)
            ->with(['process:id,name', 'jobWorker:id,name', 'items'])
            ->withCount('items')
            ->findOrFail($id);

        $issueItems = CastingMetalIssueItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        abort_if($issueItems->isEmpty(), 404);

        $releaseItems = CastingReleaseItem::where('company_id', $company->id)
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

        $customReleaseItems = CastingReleaseItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where('is_custom', true)
            ->where(function ($q) {
                $q->where('release_tree_wt', '>', 0)
                    ->orWhere('release_tree_bhuko', '>', 0);
            })
            ->orderBy('id')
            ->get();

        return [$company, $voucher, $issueItems, $releaseItems, $customReleaseItems];
    }

    private function normalizeCustomType($type, array $row): string
    {
        $type = (string) ($type ?? '');
        if (in_array($type, ['bhuko', 'pc_weight'], true)) {
            return $type;
        }

        $releaseTreeWt = $row['release_tree_wt'] ?? null;
        $releaseTreeBhuko = $row['release_tree_bhuko'] ?? null;

        return ($releaseTreeWt !== null && $releaseTreeWt !== '' && (float) $releaseTreeWt > 0)
            && !($releaseTreeBhuko !== null && $releaseTreeBhuko !== '' && (float) $releaseTreeBhuko > 0)
                ? 'pc_weight'
                : 'bhuko';
    }

    private function syncCustomTreeFlow(int $companyId, VacuumVoucher $voucher, CastingReleaseItem $customItem): void
    {
        if ($customItem->custom_type !== 'pc_weight' || (float) ($customItem->release_tree_wt ?? 0) <= 0) {
            $this->deleteCustomTreeFlow($companyId, (int) $customItem->id);
            return;
        }

        $officeItem = TreeCuttingOfficeItem::where('company_id', $companyId)
            ->where('casting_release_item_id', $customItem->id)
            ->first();
        $officeCutWt = (float) ($officeItem?->office_cut_wt ?? 0);
        $remainingTreeWt = round(max((float) $customItem->release_tree_wt - $officeCutWt, 0), 3);

        if ($officeItem) {
            $officeItem->update([
                'vacuum_voucher_item_id' => null,
                'custom_buch_no' => $customItem->custom_buch_no,
                'is_custom' => true,
                'tree_wt' => $customItem->release_tree_wt,
                'remaining_tree_wt' => $remainingTreeWt,
                'updated_by' => auth()->id(),
            ]);
        }

        $issueItem = TreeCuttingIssueItem::firstOrNew([
            'company_id' => $companyId,
            'casting_release_item_id' => $customItem->id,
        ]);

        $issueItem->fill([
            'vacuum_voucher_id' => $voucher->id,
            'vacuum_voucher_item_id' => null,
            'custom_buch_no' => $customItem->custom_buch_no,
            'is_custom' => true,
            'receive_tree_wt' => $remainingTreeWt,
            'issued_by' => auth()->id(),
            'issued_at' => now(),
        ]);

        if ($officeItem && $officeItem->issue_group_key) {
            $issueItem->issue_group_key = $officeItem->issue_group_key;
        }

        $issueItem->save();
    }

    private function deleteCustomTreeFlow(int $companyId, int $castingReleaseItemId): void
    {
        $issueIds = TreeCuttingIssueItem::where('company_id', $companyId)
            ->where('casting_release_item_id', $castingReleaseItemId)
            ->pluck('id');

        if ($issueIds->isNotEmpty()) {
            TreeCuttingReceiveItem::where('company_id', $companyId)
                ->whereIn('tree_cutting_issue_item_id', $issueIds)
                ->delete();

            TreeCuttingIssueItem::where('company_id', $companyId)
                ->whereIn('id', $issueIds)
                ->delete();
        }

        TreeCuttingOfficeItem::where('company_id', $companyId)
            ->where('casting_release_item_id', $castingReleaseItemId)
            ->delete();
    }
}
