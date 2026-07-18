<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CastingReleaseItem;
use App\Models\Company;
use App\Models\JobWorker;
use App\Models\TreeCuttingIssueItem;
use App\Models\VacuumVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class TreeCuttingIssueController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $fromDate = $request->get('from_date', now()->toDateString());
        $toDate = $request->get('to_date', now()->toDateString());
        $workerId = $request->get('worker_id');

        if ($request->ajax()) {
            $receivedRows = function ($query) use ($company) {
                $query->where('casting_release_items.company_id', $company->id)
                    ->where(function ($q) {
                        $q->whereNotNull('casting_release_items.release_tree_wt')
                            ->orWhereNotNull('casting_release_items.release_tree_bhuko');
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
                            $q->whereNotNull('casting_release_items.release_tree_wt')
                                ->orWhereNotNull('casting_release_items.release_tree_bhuko');
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
                        ->whereNotNull('tree_cutting_issue_items.receive_tree_wt');
                }, 'tree_cutting_count')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->selectRaw('COALESCE(SUM(tree_cutting_issue_items.receive_tree_wt), 0)')
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id);
                }, 'receive_tree_wt_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->leftJoin('job_workers', 'job_workers.id', '=', 'tree_cutting_issue_items.job_worker_id')
                        ->selectRaw("GROUP_CONCAT(DISTINCT job_workers.name ORDER BY job_workers.name SEPARATOR ', ')")
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id);
                }, 'tree_cutting_worker_names')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->selectRaw('MAX(COALESCE(tree_cutting_issue_items.issued_at, tree_cutting_issue_items.created_at))')
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id);
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

        $jobWorkers = JobWorker::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

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

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $voucher = VacuumVoucher::where('company_id', $company->id)->findOrFail($id);

        $validItemIds = CastingReleaseItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->whereNotNull('release_tree_wt')
                    ->orWhereNotNull('release_tree_bhuko');
            })
            ->pluck('vacuum_voucher_item_id')
            ->map(fn($itemId) => (int) $itemId)
            ->all();

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.receive_tree_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.job_worker_id' => [
                'nullable',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $company->id)),
            ],
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
            foreach (($validated['items'] ?? []) as $itemId => $row) {
                $itemId = (int) $itemId;

                if (!in_array($itemId, $validItemIds, true)) {
                    continue;
                }

                $receiveTreeWt = $row['receive_tree_wt'] ?? null;
                $jobWorkerId = $row['job_worker_id'] ?? null;

                TreeCuttingIssueItem::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'vacuum_voucher_item_id' => $itemId,
                    ],
                    [
                        'vacuum_voucher_id' => $voucher->id,
                        'job_worker_id' => $jobWorkerId !== null && $jobWorkerId !== '' ? (int) $jobWorkerId : null,
                        'is_custom' => false,
                        'custom_buch_no' => null,
                        'receive_tree_wt' => $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null,
                        'issued_by' => auth()->id(),
                        'issued_at' => now(),
                    ]
                );
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

                $issueItem->update([
                    'job_worker_id' => $jobWorkerId !== null && $jobWorkerId !== '' ? (int) $jobWorkerId : null,
                    'custom_buch_no' => $customBuchNo,
                    'receive_tree_wt' => $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null,
                    'issued_by' => auth()->id(),
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
                    'company_id' => $company->id,
                    'vacuum_voucher_id' => $voucher->id,
                    'vacuum_voucher_item_id' => null,
                    'job_worker_id' => $jobWorkerId !== null && $jobWorkerId !== '' ? (int) $jobWorkerId : null,
                    'custom_buch_no' => $customBuchNo,
                    'is_custom' => true,
                    'receive_tree_wt' => $receiveTreeWt !== null && $receiveTreeWt !== '' ? (float) $receiveTreeWt : null,
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
                $q->whereNotNull('release_tree_wt')
                    ->orWhereNotNull('release_tree_bhuko');
            })
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        abort_if($receiveItems->isEmpty(), 404);

        $treeCuttingItems = TreeCuttingIssueItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where('is_custom', false)
            ->with('jobWorker:id,name')
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        $customTreeCuttingItems = TreeCuttingIssueItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where('is_custom', true)
            ->with('jobWorker:id,name')
            ->orderBy('id')
            ->get();

        $jobWorkers = JobWorker::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return [$company, $voucher, $receiveItems, $treeCuttingItems, $customTreeCuttingItems, $jobWorkers];
    }
}
