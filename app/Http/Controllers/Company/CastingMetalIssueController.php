<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CastingHeatingItem;
use App\Models\CastingMetalIssueItem;
use App\Models\Company;
use App\Models\JobWorker;
use App\Models\VacuumVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CastingMetalIssueController extends Controller
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
                ->when($fromDate, fn($q) => $q->whereDate('voucher_date', '>=', $fromDate))
                ->when($toDate, fn($q) => $q->whereDate('voucher_date', '<=', $toDate))
                ->when($workerId, fn($q) => $q->where('job_worker_id', $workerId))
                ->with(['process:id,name', 'jobWorker:id,name'])
                ->select('vacuum_vouchers.*')
                ->withCount('items')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_metal_issue_items')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('casting_metal_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_metal_issue_items.company_id', $company->id)
                        ->whereNotNull('casting_metal_issue_items.issue_silver_wt');
                }, 'assigned_metal_count')
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('voucher_no_view', fn($row) => $row->voucher_no)
                ->addColumn('process_name', fn($row) => $row->process?->name ?? '-')
                ->addColumn('worker_name', fn($row) => $row->jobWorker?->name ?? '-')
                ->addColumn('date_time_view', fn($row) => optional($row->created_at)->format('d-m-Y  / h:i A') ?? '-')
                ->addColumn('assigned_metal_view', function ($row) {
                    $assigned = (int) ($row->assigned_metal_count ?? 0);

                    return '<span class="count-badge count-assigned">' . $assigned . '</span>';
                })
                ->addColumn('pending_metal_view', function ($row) {
                    $total = (int) ($row->items_count ?? 0);
                    $assigned = (int) ($row->assigned_metal_count ?? 0);
                    $pending = max($total - $assigned, 0);

                    return '<span class="count-badge ' . ($pending > 0 ? 'count-pending' : 'count-complete') . '">' . $pending . '</span>';
                })
                ->addColumn('action', function ($row) use ($company) {
                    $id = Crypt::encryptString((string) $row->id);
                    $view = route('company.casting-metal-issue.show', [$company->slug, $id]);
                    $pdf = route('company.casting-metal-issue.pdf', [$company->slug, $id]);

                    return '<div class="d-flex gap-1">
                        <a href="' . $view . '" class="btn btn-sm btn-info">View</a>
                        <a href="' . $pdf . '" class="btn btn-sm btn-success">PDF</a>
                    </div>';
                })
                ->rawColumns(['assigned_metal_view', 'pending_metal_view', 'action'])
                ->make(true);
        }

        $jobWorkers = JobWorker::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('company.casting_metal_issue.index', compact('company', 'fromDate', 'toDate', 'jobWorkers'));
    }

    public function show($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $voucher = VacuumVoucher::where('company_id', $company->id)
            ->with(['process:id,name', 'jobWorker:id,name', 'items'])
            ->withCount('items')
            ->findOrFail($id);

        $heatingItems = CastingHeatingItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        $issueItems = CastingMetalIssueItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        return view('company.casting_metal_issue.show', compact('company', 'voucher', 'heatingItems', 'issueItems'));
    }

    public function pdf($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $voucher = VacuumVoucher::where('company_id', $company->id)
            ->with(['process:id,name', 'jobWorker:id,name', 'items'])
            ->withCount('items')
            ->findOrFail($id);

        $heatingItems = CastingHeatingItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        $issueItems = CastingMetalIssueItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        return Pdf::loadView('company.casting_metal_issue.pdf.show', compact('company', 'voucher', 'heatingItems', 'issueItems'))
            ->setPaper('a4', 'landscape')
            ->download('casting_metal_issue_' . $voucher->voucher_no . '.pdf');
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $voucher = VacuumVoucher::where('company_id', $company->id)
            ->with('items:id,vacuum_voucher_id')
            ->findOrFail($id);

        $validItemIds = $voucher->items->pluck('id')->map(fn($itemId) => (int) $itemId)->all();

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.issue_silver_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_if' => ['nullable', 'boolean'],
            'items.*.pure_fine' => ['nullable', 'numeric', 'min:0'],
            'items.*.if_percentage' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($company, $voucher, $validItemIds, $validated) {
            foreach (($validated['items'] ?? []) as $itemId => $row) {
                $itemId = (int) $itemId;

                if (!in_array($itemId, $validItemIds, true)) {
                    continue;
                }

                $issueSilverWt = $row['issue_silver_wt'] ?? null;
                $isIf = (bool) ($row['is_if'] ?? false);
                $pureFine = $row['pure_fine'] ?? null;
                $ifPercentage = $row['if_percentage'] ?? null;
                $pureFineValue = $isIf && $pureFine !== null && $pureFine !== '' ? (float) $pureFine : null;
                $ifPercentageValue = $isIf && $ifPercentage !== null && $ifPercentage !== '' ? (float) $ifPercentage : null;
                $metalWeight = $isIf && $pureFineValue !== null && $ifPercentageValue !== null
                    ? round($pureFineValue / ($ifPercentageValue / 100), 3)
                    : null;
                $otherMetal = $metalWeight !== null && $pureFineValue !== null
                    ? round($metalWeight - $pureFineValue, 3)
                    : null;
                $remarks = trim((string) ($row['remarks'] ?? ''));

                CastingMetalIssueItem::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'vacuum_voucher_item_id' => $itemId,
                    ],
                    [
                        'vacuum_voucher_id' => $voucher->id,
                        'issue_silver_wt' => $issueSilverWt !== null && $issueSilverWt !== '' ? (float) $issueSilverWt : null,
                        'is_if' => $isIf,
                        'pure_fine' => $pureFineValue,
                        'if_percentage' => $ifPercentageValue,
                        'other_metal' => $otherMetal,
                        'metal_weight' => $metalWeight,
                        'remarks' => $remarks !== '' ? $remarks : null,
                        'issued_by' => auth()->id(),
                        'issued_at' => now(),
                    ]
                );
            }
        });

        return redirect()
            ->route('company.casting-metal-issue.index', $company->slug)
            ->with('success', 'Casting metal issue updated successfully');
    }
}
