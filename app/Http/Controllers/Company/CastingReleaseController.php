<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CastingMetalIssueItem;
use App\Models\CastingReleaseItem;
use App\Models\Company;
use App\Models\JobWorker;
use App\Models\VacuumVoucher;
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
        $fromDate = $request->get('from_date', now()->toDateString());
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
                            $q->whereNotNull('casting_release_items.release_tree_wt')
                                ->orWhereNotNull('casting_release_items.release_tree_bhuko');
                        });
                }, 'assigned_receive_count')
                ->orderByDesc('metal_issue_datetime')
                ->orderByDesc('id');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('voucher_no_view', fn($row) => $row->voucher_no)
                ->addColumn('process_name', fn($row) => $row->process?->name ?? '-')
                ->addColumn('worker_name', fn($row) => $row->jobWorker?->name ?? '-')
                ->addColumn('date_time_view', fn($row) => $row->metal_issue_datetime ? \Carbon\Carbon::parse($row->metal_issue_datetime)->format('d-m-Y / h:i A') : '-')
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

        $jobWorkers = JobWorker::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('company.casting_release.index', compact('company', 'fromDate', 'toDate', 'jobWorkers'));
    }

    public function show($slug, $encryptedId)
    {
        [$company, $voucher, $issueItems, $releaseItems] = $this->voucherData($slug, $encryptedId);

        return view('company.casting_release.show', compact('company', 'voucher', 'issueItems', 'releaseItems'));
    }

    public function pdf($slug, $encryptedId)
    {
        [$company, $voucher, $issueItems, $releaseItems] = $this->voucherData($slug, $encryptedId);

        return Pdf::loadView('company.casting_release.pdf.show', compact('company', 'voucher', 'issueItems', 'releaseItems'))
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
                        'release_tree_wt' => $releaseTreeWtValue,
                        'release_tree_bhuko' => $releaseTreeBhukoValue,
                        'loss' => $loss,
                        'released_by' => auth()->id(),
                        'released_at' => now(),
                    ]
                );
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
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        return [$company, $voucher, $issueItems, $releaseItems];
    }
}
