<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JobWorker;
use App\Models\TreeCuttingIssueItem;
use App\Models\TreeCuttingReceiveItem;
use App\Models\VacuumVoucher;
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
        $fromDate = $request->get('from_date', now()->toDateString());
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
                        ->whereNotNull('tree_cutting_issue_items.receive_tree_wt')
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
                            ->whereNotNull('tree_cutting_issue_items.receive_tree_wt');
                    });
                })
                ->with(['process:id,name', 'jobWorker:id,name'])
                ->select('vacuum_vouchers.*')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->selectRaw('MAX(COALESCE(tree_cutting_issue_items.issued_at, tree_cutting_issue_items.created_at))')
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id);
                }, 'tree_cutting_issue_datetime')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id)
                        ->whereNotNull('tree_cutting_issue_items.receive_tree_wt');
                }, 'tree_cutting_issue_count')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_receive_items')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_receive_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->whereNotNull('tree_cutting_receive_items.receive_pc_wt')
                                ->orWhereNotNull('tree_cutting_receive_items.receive_tree_bhuko');
                        });
                }, 'tree_cutting_receive_count')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_receive_items')
                        ->selectRaw('COALESCE(SUM(tree_cutting_receive_items.receive_pc_wt), 0)')
                        ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_receive_items.company_id', $company->id);
                }, 'receive_pc_wt_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_receive_items')
                        ->selectRaw('COALESCE(SUM(tree_cutting_receive_items.receive_tree_bhuko), 0)')
                        ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_receive_items.company_id', $company->id);
                }, 'receive_tree_bhuko_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_receive_items')
                        ->selectRaw('COALESCE(SUM(tree_cutting_receive_items.loss), 0)')
                        ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_receive_items.company_id', $company->id);
                }, 'loss_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_issue_items')
                        ->leftJoin('job_workers', 'job_workers.id', '=', 'tree_cutting_issue_items.job_worker_id')
                        ->selectRaw("GROUP_CONCAT(DISTINCT job_workers.name ORDER BY job_workers.name SEPARATOR ', ')")
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id)
                        ->whereNotNull('tree_cutting_issue_items.receive_tree_wt');
                }, 'tree_cutting_worker_names')
                ->orderByDesc('tree_cutting_issue_datetime')
                ->orderByDesc('id');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('voucher_no_view', fn($row) => $row->voucher_no)
                ->addColumn('date_time_view', fn($row) => $row->tree_cutting_issue_datetime ? \Carbon\Carbon::parse($row->tree_cutting_issue_datetime)->format('d-m-Y  / h:i A') : '-')
                ->addColumn('process_name', fn($row) => $row->process?->name ?? '-')
                ->addColumn('worker_name', fn($row) => $row->tree_cutting_worker_names ?: ($row->jobWorker?->name ?? '-'))
                ->addColumn('assigned_receive_view', fn($row) => '<span class="count-badge count-assigned">' . (int) ($row->tree_cutting_receive_count ?? 0) . '</span>')
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

        $jobWorkers = JobWorker::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('company.tree_cutting_receive.index', compact('company', 'fromDate', 'toDate', 'jobWorkers'));
    }

    public function show($slug, $encryptedId)
    {
        [$company, $voucher, $issueItems, $receiveItems] = $this->voucherData($slug, $encryptedId);

        return view('company.tree_cutting_receive.show', compact('company', 'voucher', 'issueItems', 'receiveItems'));
    }

    public function pdf($slug, $encryptedId)
    {
        [$company, $voucher, $issueItems, $receiveItems] = $this->voucherData($slug, $encryptedId);

        return Pdf::loadView('company.tree_cutting_receive.pdf.show', compact('company', 'voucher', 'issueItems', 'receiveItems'))
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
            ->whereNotNull('receive_tree_wt')
            ->get()
            ->keyBy('id');

        $validItemIds = $issueItems->keys()->map(fn($itemId) => (int) $itemId)->all();

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.receive_pc_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.receive_tree_bhuko' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($company, $voucher, $issueItems, $validItemIds, $validated) {
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
                        'company_id' => $company->id,
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
                        'received_by' => auth()->id(),
                        'received_at' => now(),
                    ]
                );
            }
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
            ->whereNotNull('receive_tree_wt')
            ->with(['voucherItem:id,buch_no', 'jobWorker:id,name'])
            ->orderBy('is_custom')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        abort_if($issueItems->isEmpty(), 404);

        $receiveItems = TreeCuttingReceiveItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy('tree_cutting_issue_item_id');

        return [$company, $voucher, $issueItems, $receiveItems];
    }
}
