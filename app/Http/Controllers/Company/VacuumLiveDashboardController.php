<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\VacuumProcess;
use App\Models\VacuumVoucher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class VacuumLiveDashboardController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $date = $request->input('date', now()->toDateString());
        $workerId = $request->input('worker_id');
        $processId = $request->input('process_id');
        $voucherNo = trim((string) $request->input('voucher_no', ''));

        $vouchers = $this->voucherQuery($company, $date, $workerId, $processId, $voucherNo)
            ->with(['process:id,name', 'jobWorker:id,name'])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->get();

        $voucherIds = $vouchers->pluck('id')->values();
        $stageCounts = $this->stageCounts($company->id, $voucherIds);
        $rows = $vouchers->map(fn($voucher) => $this->dashboardRow($company, $voucher, $stageCounts))->values();
        $summary = $this->summary($rows);
        $inBhatiRows = $this->inBhatiRows($company->id, $voucherIds);

        $workers = DB::table('job_workers')
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $processes = VacuumProcess::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $voucherOptions = $this->voucherQuery($company, $date, $workerId, $processId, '')
            ->with(['jobWorker:id,name'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'voucher_no', 'job_worker_id', 'created_at'])
            ->map(fn($voucher) => [
                'voucher_no' => $voucher->voucher_no,
                'label' => trim($voucher->voucher_no . ' - ' . optional($voucher->created_at)->format('h:i A') . ' - ' . ($voucher->jobWorker?->name ?? '-')),
            ])
            ->values();

        return view('company.vacuum_live_dashboard.index', compact(
            'company',
            'date',
            'workerId',
            'processId',
            'voucherNo',
            'workers',
            'processes',
            'voucherOptions',
            'summary',
            'rows',
            'inBhatiRows'
        ));
    }

    private function voucherQuery(Company $company, string $date, $workerId, $processId, string $voucherNo)
    {
        return VacuumVoucher::query()
            ->where('company_id', $company->id)
            ->whereDate('created_at', $date)
            ->when($workerId, fn($q) => $q->where('job_worker_id', (int) $workerId))
            ->when($processId, fn($q) => $q->where('vacuum_process_id', (int) $processId))
            ->when($voucherNo !== '', fn($q) => $q->where('voucher_no', 'like', "%{$voucherNo}%"));
    }

    private function stageCounts(int $companyId, $voucherIds): array
    {
        if ($voucherIds->isEmpty()) {
            return [];
        }

        return [
            'heating' => DB::table('casting_heating_items')
                ->where('company_id', $companyId)
                ->whereIn('vacuum_voucher_id', $voucherIds)
                ->where('in_bhati', true)
                ->select('vacuum_voucher_id', DB::raw('COUNT(*) as total'))
                ->groupBy('vacuum_voucher_id')
                ->pluck('total', 'vacuum_voucher_id'),
            'metal_issue' => DB::table('casting_metal_issue_items')
                ->where('company_id', $companyId)
                ->whereIn('vacuum_voucher_id', $voucherIds)
                ->whereNotNull('issue_silver_wt')
                ->select('vacuum_voucher_id', DB::raw('COUNT(*) as total'))
                ->groupBy('vacuum_voucher_id')
                ->pluck('total', 'vacuum_voucher_id'),
            'casting_receive' => DB::table('casting_release_items')
                ->where('company_id', $companyId)
                ->whereIn('vacuum_voucher_id', $voucherIds)
                ->where(function ($q) {
                    $q->whereNotNull('release_tree_wt')
                        ->orWhereNotNull('release_tree_bhuko');
                })
                ->select('vacuum_voucher_id', DB::raw('COUNT(*) as total'))
                ->groupBy('vacuum_voucher_id')
                ->pluck('total', 'vacuum_voucher_id'),
            'tree_issue' => DB::table('tree_cutting_issue_items')
                ->where('company_id', $companyId)
                ->whereIn('vacuum_voucher_id', $voucherIds)
                ->whereNotNull('receive_tree_wt')
                ->select('vacuum_voucher_id', DB::raw('COUNT(*) as total'))
                ->groupBy('vacuum_voucher_id')
                ->pluck('total', 'vacuum_voucher_id'),
            'tree_receive' => DB::table('tree_cutting_receive_items')
                ->where('company_id', $companyId)
                ->whereIn('vacuum_voucher_id', $voucherIds)
                ->where(function ($q) {
                    $q->whereNotNull('receive_pc_wt')
                        ->orWhereNotNull('receive_tree_bhuko');
                })
                ->select('vacuum_voucher_id', DB::raw('COUNT(*) as total'))
                ->groupBy('vacuum_voucher_id')
                ->pluck('total', 'vacuum_voucher_id'),
            'sorting' => DB::table('casting_sorting_items')
                ->where('company_id', $companyId)
                ->whereIn('vacuum_voucher_id', $voucherIds)
                ->select('vacuum_voucher_id', DB::raw('COALESCE(SUM(quantity), 0) as total'))
                ->groupBy('vacuum_voucher_id')
                ->pluck('total', 'vacuum_voucher_id'),
        ];
    }

    private function dashboardRow(Company $company, VacuumVoucher $voucher, array $stageCounts): array
    {
        $total = (int) ($voucher->items_count ?? 0);
        $counts = [
            'heating' => (int) data_get($stageCounts, "heating.{$voucher->id}", 0),
            'metal_issue' => (int) data_get($stageCounts, "metal_issue.{$voucher->id}", 0),
            'casting_receive' => (int) data_get($stageCounts, "casting_receive.{$voucher->id}", 0),
            'tree_issue' => (int) data_get($stageCounts, "tree_issue.{$voucher->id}", 0),
            'tree_receive' => (int) data_get($stageCounts, "tree_receive.{$voucher->id}", 0),
            'sorting' => (int) data_get($stageCounts, "sorting.{$voucher->id}", 0),
        ];

        return [
            'id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'created_time' => optional($voucher->created_at)->format('d-m-Y h:i A'),
            'process' => $voucher->process?->name ?? '-',
            'worker' => $voucher->jobWorker?->name ?? '-',
            'total_pcs' => $total,
            'counts' => $counts,
            'current_stage' => $this->currentStage($counts, $total),
            'voucher_url' => route('company.vacuum-vouchers.show', [$company->slug, Crypt::encryptString((string) $voucher->id)]),
            'history_url' => route('company.voucher-history.index', $company->slug) . '?voucher_id=' . $voucher->id,
        ];
    }

    private function currentStage(array $counts, int $total): string
    {
        $stages = [
            'sorting' => 'Sorting',
            'tree_receive' => 'Tree Receive',
            'tree_issue' => 'Tree Issue',
            'casting_receive' => 'Casting Receive',
            'metal_issue' => 'Metal Issue',
            'heating' => 'In Bhati',
        ];

        if ($total > 0 && ($counts['sorting'] ?? 0) >= $total) {
            return 'Completed';
        }

        foreach ($stages as $key => $label) {
            $count = $counts[$key] ?? 0;
            if ($count > 0) {
                return $count >= $total ? "{$label} Done" : "{$label} Partial";
            }
        }

        return 'Voucher Created';
    }

    private function summary($rows): array
    {
        $totalPcs = (int) $rows->sum('total_pcs');

        return [
            'vouchers' => $rows->count(),
            'total_pcs' => $totalPcs,
            'in_bhati_pcs' => (int) $rows->sum(fn($row) => $row['counts']['heating']),
            'pending_metal_issue' => max(0, $totalPcs - (int) $rows->sum(fn($row) => $row['counts']['metal_issue'])),
            'pending_casting_receive' => max(0, $totalPcs - (int) $rows->sum(fn($row) => $row['counts']['casting_receive'])),
            'pending_tree_issue' => max(0, $totalPcs - (int) $rows->sum(fn($row) => $row['counts']['tree_issue'])),
            'pending_tree_receive' => max(0, $totalPcs - (int) $rows->sum(fn($row) => $row['counts']['tree_receive'])),
            'pending_sorting' => max(0, $totalPcs - (int) $rows->sum(fn($row) => $row['counts']['sorting'])),
            'completed_vouchers' => $rows->filter(fn($row) => $row['total_pcs'] > 0 && $row['counts']['sorting'] >= $row['total_pcs'])->count(),
        ];
    }

    private function inBhatiRows(int $companyId, $voucherIds)
    {
        if ($voucherIds->isEmpty()) {
            return collect();
        }

        return DB::table('casting_heating_items as chi')
            ->join('vacuum_vouchers as vv', 'vv.id', '=', 'chi.vacuum_voucher_id')
            ->leftJoin('vacuum_voucher_items as vvi', 'vvi.id', '=', 'chi.vacuum_voucher_item_id')
            ->leftJoin('job_workers as jw', 'jw.id', '=', 'vv.job_worker_id')
            ->where('chi.company_id', $companyId)
            ->whereIn('chi.vacuum_voucher_id', $voucherIds)
            ->where('chi.in_bhati', true)
            ->orderByDesc(DB::raw('COALESCE(chi.checked_at, chi.created_at)'))
            ->get([
                'vv.voucher_no',
                'vvi.buch_no',
                'jw.name as worker_name',
                DB::raw('COALESCE(chi.checked_at, chi.created_at) as checked_time'),
            ])
            ->map(function ($row) {
                $checkedAt = $row->checked_time ? Carbon::parse($row->checked_time) : null;
                $row->checked_time_view = $checkedAt ? $checkedAt->format('d-m-Y h:i A') : '-';
                $row->duration = $checkedAt ? $checkedAt->diffForHumans(null, true) : '-';
                return $row;
            });
    }
}
