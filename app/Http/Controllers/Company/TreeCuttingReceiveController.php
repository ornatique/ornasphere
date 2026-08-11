<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
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

class TreeCuttingReceiveController extends Controller
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
                        ->from('tree_cutting_issue_items')
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id)
                        ->whereNotNull('tree_cutting_issue_items.job_worker_id')
                        ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0)
                        ->when($fromDate, fn($q) => $q->whereDate(DB::raw('COALESCE(tree_cutting_issue_items.issued_at, tree_cutting_issue_items.created_at)'), '>=', $fromDate))
                        ->when($toDate, fn($q) => $q->whereDate(DB::raw('COALESCE(tree_cutting_issue_items.issued_at, tree_cutting_issue_items.created_at)'), '<=', $toDate));
                })
                ->when($workerId, function ($q) use ($company, $workerId) {
                    $q->whereExists(function ($query) use ($company, $workerId) {
                        $query->selectRaw('1')
                            ->from('tree_cutting_issue_items')
                            ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                            ->where('tree_cutting_issue_items.company_id', $company->id)
                            ->where('tree_cutting_issue_items.job_worker_id', $workerId)
                            ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0);
                    });
                })
                ->with(['process:id,name', 'jobWorker:id,name'])
                ->select('vacuum_vouchers.*')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->selectRaw('MAX(COALESCE(tree_cutting_issue_items.issued_at, tree_cutting_issue_items.created_at))')
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id)
                        ->whereNotNull('tree_cutting_issue_items.job_worker_id')
                        ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0);
                }, 'tree_cutting_issue_datetime')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->selectRaw("COUNT(DISTINCT COALESCE(tree_cutting_issue_items.issue_group_key, CONCAT('item_', tree_cutting_issue_items.id)))")
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id)
                        ->whereNotNull('tree_cutting_issue_items.job_worker_id')
                        ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0);
                }, 'tree_cutting_issue_count')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_receive_items')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_receive_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->where('tree_cutting_receive_items.receive_pc_wt', '>', 0)
                                ->orWhere('tree_cutting_receive_items.receive_tree_bhuko', '>', 0);
                        });
                }, 'tree_cutting_receive_count')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_office_items')
                        ->selectRaw('COALESCE(SUM(tree_cutting_office_items.office_cut_wt), 0)')
                        ->whereColumn('tree_cutting_office_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_office_items.company_id', $company->id);
                }, 'office_cut_wt_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->selectRaw('COALESCE(SUM(tree_cutting_issue_items.receive_tree_wt), 0)')
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id)
                        ->whereNotNull('tree_cutting_issue_items.job_worker_id')
                        ->where('tree_cutting_issue_items.receive_tree_wt', '>', 0);
                }, 'issue_tree_wt_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_receive_items')
                        ->selectRaw('COALESCE(SUM(tree_cutting_receive_items.receive_pc_wt), 0)')
                        ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_receive_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->where('tree_cutting_receive_items.receive_pc_wt', '>', 0)
                                ->orWhere('tree_cutting_receive_items.receive_tree_bhuko', '>', 0);
                        });
                }, 'receive_pc_wt_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_receive_items')
                        ->selectRaw('COALESCE(SUM(tree_cutting_receive_items.receive_tree_bhuko), 0)')
                        ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_receive_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->where('tree_cutting_receive_items.receive_pc_wt', '>', 0)
                                ->orWhere('tree_cutting_receive_items.receive_tree_bhuko', '>', 0);
                        });
                }, 'receive_tree_bhuko_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_receive_items')
                        ->selectRaw('COALESCE(SUM(tree_cutting_receive_items.loss), 0)')
                        ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_receive_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->where('tree_cutting_receive_items.receive_pc_wt', '>', 0)
                                ->orWhere('tree_cutting_receive_items.receive_tree_bhuko', '>', 0);
                        });
                }, 'loss_total')
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
                    $query->from('tree_cutting_receive_items')
                        ->selectRaw('MAX(COALESCE(tree_cutting_receive_items.received_at, tree_cutting_receive_items.created_at))')
                        ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_receive_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->where('tree_cutting_receive_items.receive_pc_wt', '>', 0)
                                ->orWhere('tree_cutting_receive_items.receive_tree_bhuko', '>', 0);
                        });
                }, 'tree_cutting_receive_datetime')
                ->orderByDesc('tree_cutting_issue_datetime')
                ->orderByDesc('id');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('voucher_no_view', fn($row) => $row->voucher_no)
                ->addColumn('date_time_view', fn($row) => $row->tree_cutting_receive_datetime ? \Carbon\Carbon::parse($row->tree_cutting_receive_datetime)->format('d-m-Y / h:i A') : ($row->tree_cutting_issue_datetime ? \Carbon\Carbon::parse($row->tree_cutting_issue_datetime)->format('d-m-Y / h:i A') : '-'))
                ->addColumn('process_name', fn($row) => $row->process?->name ?? '-')
                ->addColumn('worker_name', fn($row) => $row->tree_cutting_worker_names ?: ($row->jobWorker?->name ?? '-'))
                ->addColumn('assigned_receive_view', fn($row) => '<span class="count-badge count-assigned">' . (int) ($row->tree_cutting_receive_count ?? 0) . '</span>')
                ->addColumn('office_cut_wt_view', fn($row) => number_format((float) ($row->office_cut_wt_total ?? 0), 3, '.', ''))
                ->addColumn('issue_tree_wt_view', fn($row) => number_format((float) ($row->issue_tree_wt_total ?? 0), 3, '.', ''))
                ->addColumn('receive_pc_wt_view', fn($row) => number_format((float) ($row->receive_pc_wt_total ?? 0), 3, '.', ''))
                ->addColumn('receive_tree_bhuko_view', fn($row) => number_format((float) ($row->receive_tree_bhuko_total ?? 0), 3, '.', ''))
                ->addColumn('loss_view', fn($row) => number_format((float) ($row->loss_total ?? 0), 3, '.', ''))
                ->addColumn('pending_receive_view', function ($row) {
                    $issue = (int) ($row->tree_cutting_issue_count ?? 0);
                    $received = (int) ($row->tree_cutting_receive_count ?? 0);
                    $pending = max($issue - $received, 0);

                    return '<span class="count-badge ' . ($pending > 0 ? 'count-pending' : 'count-complete') . '">' . $pending . '</span>';
                })
                ->addColumn('action', function ($row) use ($company) {
                    $id = Crypt::encryptString((string) $row->id);
                    $view = route('company.tree-cutting-receive.show', [$company->slug, $id]);
                    $pdf = route('company.tree-cutting-receive.pdf', [$company->slug, $id]);

                    return '<div class="d-flex gap-1">
                        <a href="' . $view . '" class="btn btn-sm btn-info">View</a>
                        <a href="' . $pdf . '" class="btn btn-sm btn-success">PDF</a>
                    </div>';
                })
                ->rawColumns(['assigned_receive_view', 'pending_receive_view', 'action'])
                ->make(true);
        }

        $jobWorkers = WorkerPersonService::activeWorkers((int) $company->id);

        return view('company.tree_cutting_receive.index', compact('company', 'fromDate', 'toDate', 'jobWorkers'));
    }

    public function show($slug, $encryptedId)
    {
        [$company, $voucher, $issueItems, $receiveItems, $customReceiveItems] = $this->voucherData($slug, $encryptedId);

        return view('company.tree_cutting_receive.show', compact('company', 'voucher', 'issueItems', 'receiveItems', 'customReceiveItems'));
    }

    public function pdf($slug, $encryptedId)
    {
        [$company, $voucher, $issueItems, $receiveItems, $customReceiveItems] = $this->voucherData($slug, $encryptedId);

        return Pdf::loadView('company.tree_cutting_receive.pdf.show', compact('company', 'voucher', 'issueItems', 'receiveItems', 'customReceiveItems'))
            ->setPaper('a4', 'portrait')
            ->download('tree_cutting_receive_' . $voucher->voucher_no . '.pdf');
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $voucher = VacuumVoucher::where('company_id', $company->id)->findOrFail($id);

        $issueItems = TreeCuttingIssueItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->whereNotNull('job_worker_id')
            ->where('receive_tree_wt', '>', 0)
            ->with(['voucherItem:id,buch_no', 'jobWorker:id,name'])
            ->get()
            ->values();

        $officeItems = $this->officeItemsForVoucher($company->id, $voucher->id);
        $issueRows = $this->issueReceiveRows($issueItems, $officeItems);
        $validItemKeys = $issueRows->keys()->all();

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.receive_pc_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.receive_tree_bhuko' => ['nullable', 'numeric', 'min:0'],
            'custom_items' => ['nullable', 'array'],
            'custom_items.*.id' => ['nullable', 'integer'],
            'custom_items.*.custom_type' => ['nullable', 'in:bhuko,pc_weight'],
            'custom_items.*.custom_buch_no' => ['nullable', 'string', 'max:100'],
            'custom_items.*.receive_pc_wt' => ['nullable', 'numeric', 'min:0'],
            'custom_items.*.receive_tree_bhuko' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($company, $voucher, $issueRows, $validItemKeys, $validated) {
            foreach (($validated['items'] ?? []) as $issueItemKey => $row) {
                $issueItemKey = (string) $issueItemKey;

                if (!in_array($issueItemKey, $validItemKeys, true)) {
                    continue;
                }

                $issueRow = $issueRows->get($issueItemKey);
                $issueItem = $issueRow->primary_item;
                $issueTreeWt = (float) ($issueRow->receive_tree_wt ?? 0);
                $receivePcWt = $row['receive_pc_wt'] ?? null;
                $receiveTreeBhuko = $row['receive_tree_bhuko'] ?? null;
                $receivePcWtValue = $receivePcWt !== null && $receivePcWt !== '' ? (float) $receivePcWt : null;
                $receiveTreeBhukoValue = $receiveTreeBhuko !== null && $receiveTreeBhuko !== '' ? (float) $receiveTreeBhuko : null;

                $receiveQuery = TreeCuttingReceiveItem::where('company_id', $company->id)
                    ->where(function ($query) use ($issueRow) {
                        $query->whereIn('tree_cutting_issue_item_id', $issueRow->issue_item_ids);
                        if ($issueRow->issue_group_keys) {
                            $query->orWhereIn('issue_group_key', $issueRow->issue_group_keys);
                        }
                    });

                if (($receivePcWtValue === null || $receivePcWtValue <= 0) && ($receiveTreeBhukoValue === null || $receiveTreeBhukoValue <= 0)) {
                    $receiveQuery->delete();
                    continue;
                }

                $loss = $receivePcWtValue !== null || $receiveTreeBhukoValue !== null
                    ? round(($receivePcWtValue ?? 0) + ($receiveTreeBhukoValue ?? 0) - $issueTreeWt, 3)
                    : null;

                $receiveQuery->delete();

                TreeCuttingReceiveItem::create([
                    'company_id' => $company->id,
                    'vacuum_voucher_id' => $voucher->id,
                    'vacuum_voucher_item_id' => $issueItem?->vacuum_voucher_item_id,
                    'tree_cutting_issue_item_id' => $issueItem?->id,
                    'issue_group_key' => count($issueRow->issue_group_keys) === 1 ? $issueRow->issue_group_keys[0] : null,
                    'job_worker_id' => $issueItem?->job_worker_id,
                    'custom_buch_no' => $issueRow->buch_no,
                    'is_custom' => (bool) ($issueItem?->is_custom),
                    'receive_pc_wt' => $receivePcWtValue,
                    'receive_tree_bhuko' => $receiveTreeBhukoValue,
                    'loss' => $loss,
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                ]);
            }

            $submittedCustomIds = [];
            foreach (($validated['custom_items'] ?? []) as $row) {
                $customId = (int) ($row['id'] ?? 0);
                $customBuchNo = trim((string) ($row['custom_buch_no'] ?? ''));
                $customType = $this->normalizeCustomType($row['custom_type'] ?? null, $row);
                $receivePcWt = $row['receive_pc_wt'] ?? null;
                $receiveTreeBhuko = $row['receive_tree_bhuko'] ?? null;
                $receivePcWtValue = $receivePcWt !== null && $receivePcWt !== '' ? (float) $receivePcWt : null;
                $receiveTreeBhukoValue = $receiveTreeBhuko !== null && $receiveTreeBhuko !== '' ? (float) $receiveTreeBhuko : null;
                if ($customType === 'pc_weight') {
                    $receiveTreeBhukoValue = null;
                } else {
                    $receivePcWtValue = null;
                }

                if (
                    $customBuchNo === ''
                    && ($receivePcWtValue === null || $receivePcWtValue <= 0)
                    && ($receiveTreeBhukoValue === null || $receiveTreeBhukoValue <= 0)
                ) {
                    if ($customId > 0) {
                        TreeCuttingReceiveItem::where('company_id', $company->id)
                            ->where('vacuum_voucher_id', $voucher->id)
                            ->where('is_custom', true)
                            ->whereNull('tree_cutting_issue_item_id')
                            ->where('id', $customId)
                            ->delete();
                    }
                    continue;
                }

                if (
                    $customBuchNo === ''
                    || (($receivePcWtValue === null || $receivePcWtValue <= 0) && ($receiveTreeBhukoValue === null || $receiveTreeBhukoValue <= 0))
                ) {
                    continue;
                }

                $payload = [
                    'company_id' => $company->id,
                    'vacuum_voucher_id' => $voucher->id,
                    'vacuum_voucher_item_id' => null,
                    'tree_cutting_issue_item_id' => null,
                    'issue_group_key' => null,
                    'job_worker_id' => null,
                    'custom_buch_no' => $customBuchNo,
                    'is_custom' => true,
                    'receive_pc_wt' => $receivePcWtValue,
                    'receive_tree_bhuko' => $receiveTreeBhukoValue,
                    'loss' => round(($receivePcWtValue ?? 0) + ($receiveTreeBhukoValue ?? 0), 3),
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                ];

                if ($customId > 0) {
                    $customItem = TreeCuttingReceiveItem::where('company_id', $company->id)
                        ->where('vacuum_voucher_id', $voucher->id)
                        ->where('is_custom', true)
                        ->whereNull('tree_cutting_issue_item_id')
                        ->where('id', $customId)
                        ->first();

                    if ($customItem) {
                        $customItem->update($payload);
                        $submittedCustomIds[] = $customItem->id;
                        continue;
                    }
                }

                $customItem = TreeCuttingReceiveItem::create($payload);
                $submittedCustomIds[] = $customItem->id;
            }

            TreeCuttingReceiveItem::where('company_id', $company->id)
                ->where('vacuum_voucher_id', $voucher->id)
                ->where('is_custom', true)
                ->whereNull('tree_cutting_issue_item_id')
                ->when(!empty($submittedCustomIds), fn($query) => $query->whereNotIn('id', $submittedCustomIds))
                ->delete();
        });

        return redirect()
            ->route('company.tree-cutting-receive.index', $company->slug)
            ->with('success', 'Tree cutting receive updated successfully');
    }

    private function voucherData($slug, $encryptedId): array
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $voucher = VacuumVoucher::where('company_id', $company->id)
            ->with(['process:id,name', 'jobWorker:id,name', 'items'])
            ->withCount('items')
            ->findOrFail($id);

        $issueItems = TreeCuttingIssueItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->whereNotNull('job_worker_id')
            ->where('receive_tree_wt', '>', 0)
            ->with(['voucherItem:id,buch_no', 'jobWorker:id,name'])
            ->orderBy('is_custom')
            ->orderBy('id')
            ->get();

        abort_if($issueItems->isEmpty(), 404);

        $officeItems = $this->officeItemsForVoucher($company->id, $voucher->id);
        $issueRows = $this->issueReceiveRows($issueItems, $officeItems);

        $receiveItems = TreeCuttingReceiveItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->where('is_custom', false)
                    ->orWhereNull('is_custom')
                    ->orWhereNotNull('tree_cutting_issue_item_id');
            })
            ->where(function ($q) {
                $q->where('receive_pc_wt', '>', 0)
                    ->orWhere('receive_tree_bhuko', '>', 0);
            })
            ->get();

        $customReceiveItems = TreeCuttingReceiveItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where('is_custom', true)
            ->whereNull('tree_cutting_issue_item_id')
            ->where(function ($q) {
                $q->where('receive_pc_wt', '>', 0)
                    ->orWhere('receive_tree_bhuko', '>', 0);
            })
            ->orderBy('id')
            ->get();

        return [$company, $voucher, $issueRows, $this->receiveItemsByIssueRows($receiveItems, $issueRows), $customReceiveItems];
    }

    private function normalizeCustomType($type, array $row): string
    {
        $type = (string) ($type ?? '');
        if (in_array($type, ['bhuko', 'pc_weight'], true)) {
            return $type;
        }

        $receivePcWt = $row['receive_pc_wt'] ?? null;
        $receiveTreeBhuko = $row['receive_tree_bhuko'] ?? null;

        return ($receivePcWt !== null && $receivePcWt !== '' && (float) $receivePcWt > 0)
            && !($receiveTreeBhuko !== null && $receiveTreeBhuko !== '' && (float) $receiveTreeBhuko > 0)
                ? 'pc_weight'
                : 'bhuko';
    }

    private function officeItemsForVoucher(int $companyId, int $voucherId)
    {
        return TreeCuttingOfficeItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucherId)
            ->get()
            ->keyBy(fn($item) => $this->treeSourceKey($item));
    }

    private function issueReceiveRows($issueItems, $officeItems)
    {
        return $issueItems
            ->groupBy(fn($item) => $item->issue_group_key ?: 'item_' . $item->id)
            ->map(function ($items, $key) use ($officeItems) {
                $primary = $items->first();
                $buchNos = $items
                    ->map(fn($item) => $item->is_custom ? $item->custom_buch_no : ($item->voucherItem?->buch_no ?? '-'))
                    ->filter()
                    ->values()
                    ->implode(', ');

                return (object) [
                    'id' => $key,
                    'issue_item_ids' => $items->pluck('id')->map(fn($id) => (int) $id)->values()->all(),
                    'issue_group_keys' => $items->pluck('issue_group_key')->filter()->unique()->values()->all(),
                    'primary_item' => $primary,
                    'buch_no' => $buchNos,
                    'jobWorker' => $primary->jobWorker,
                    'office_cut_wt' => $items->sum(fn($item) => (float) ($officeItems->get($this->treeSourceKey($item))?->office_cut_wt ?? 0)),
                    'receive_tree_wt' => $items->sum(fn($item) => (float) ($item->receive_tree_wt ?? 0)),
                ];
            });
    }

    private function treeSourceKey($item): string
    {
        if ((bool) ($item->is_custom ?? false) && !empty($item->casting_release_item_id)) {
            return 'custom_' . $item->casting_release_item_id;
        }

        return (string) $item->vacuum_voucher_item_id;
    }

    private function receiveItemsByIssueRows($receiveItems, $issueRows)
    {
        return $issueRows->mapWithKeys(function ($issueRow) use ($receiveItems) {
            $matchedItems = $receiveItems->filter(function ($item) use ($issueRow) {
                return in_array((int) $item->tree_cutting_issue_item_id, $issueRow->issue_item_ids, true)
                    || ($item->issue_group_key && in_array($item->issue_group_key, $issueRow->issue_group_keys, true));
            });

            $receiveItem = $matchedItems->first();
            if ($receiveItem && $matchedItems->count() > 1) {
                $receiveItem = clone $receiveItem;
                $receiveItem->receive_pc_wt = $matchedItems->sum(fn($item) => (float) ($item->receive_pc_wt ?? 0));
                $receiveItem->receive_tree_bhuko = $matchedItems->sum(fn($item) => (float) ($item->receive_tree_bhuko ?? 0));
                $receiveItem->loss = $matchedItems->sum(fn($item) => (float) ($item->loss ?? 0));
            }

            return [$issueRow->id => $receiveItem];
        });
    }
}
