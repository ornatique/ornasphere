<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\ApprovalHeader;
use App\Models\ApprovalItem;
use App\Models\Customer;
use App\Models\ItemSet;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Services\LiveMetalRateService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CompanyDashboardController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        $canViewDashboardData = $user->hasRole('company_admin') || $user->can('dashboard-view');

        if (!$canViewDashboardData) {
            return view('company.dashboard', [
                'canViewDashboardData' => false,
            ]);
        }

        $dashboardBranches = \App\Services\CompanyBranches::visibleCompanies($user);
        $canManageBranches = \App\Services\CompanyBranches::canManage($user);
        $dashboardBranch = (string) $request->input('branch', $canManageBranches ? 'all' : $user->company->slug);
        if ($dashboardBranch === 'all') {
            abort_unless($canManageBranches, 403);
            $companyIds = $dashboardBranches->pluck('id')->all();
        } else {
            $selected = $dashboardBranches->firstWhere('slug', $dashboardBranch);
            abort_unless($selected, 403);
            $companyIds = [$selected->id];
        }
        $branchNames = $dashboardBranches->pluck('name', 'id');

        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $usersCount = User::whereIn('company_id', $companyIds)->count();
        $customersCount = Customer::whereIn('company_id', $companyIds)->count();
        $activeCustomersCount = Customer::whereIn('company_id', $companyIds)->where('is_active', 1)->count();

        $salesToday = (float) Sale::whereIn('company_id', $companyIds)
            ->whereDate('sale_date', $today)
            ->sum('net_total');

        $salesMonth = (float) Sale::whereIn('company_id', $companyIds)
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('net_total');

        $returnsMonth = (float) SaleReturn::whereIn('company_id', $companyIds)
            ->whereBetween('return_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('return_total');

        $approvalsOpen = ApprovalHeader::whereIn('company_id', $companyIds)
            ->whereIn('status', ['open', 'partial'])
            ->count();

        $pendingApprovalItems = ApprovalItem::whereHas('approval', function ($q) use ($companyIds) {
            $q->whereIn('company_id', $companyIds);
        })->where('status', 'pending')->count();

        $labelsInStock = ItemSet::whereIn('company_id', $companyIds)
            ->where('is_final', 1)
            ->where('is_sold', 0)
            ->count();

        $monthlyLabels = [];
        $monthlySales = [];
        $monthlyReturns = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $start = $month->copy()->startOfMonth()->toDateString();
            $end = $month->copy()->endOfMonth()->toDateString();

            $monthlyLabels[] = $month->format('M Y');
            $monthlySales[] = (float) Sale::whereIn('company_id', $companyIds)
                ->whereBetween('sale_date', [$start, $end])
                ->sum('net_total');
            $monthlyReturns[] = (float) SaleReturn::whereIn('company_id', $companyIds)
                ->whereBetween('return_date', [$start, $end])
                ->sum('return_total');
        }

        $recentSales = Sale::with('customer')
            ->whereIn('company_id', $companyIds)
            ->latest('sale_date')
            ->limit(5)
            ->get()
            ->map(function ($sale) use ($branchNames) {
                return [
                    'branch' => $branchNames[$sale->company_id] ?? '-',
                    'type' => 'Sale',
                    'number' => $sale->voucher_no,
                    'customer' => optional($sale->customer)->name ?: '-',
                    'date' => $sale->sale_date,
                    'amount' => (float) ($sale->net_total ?? $sale->total_amount ?? 0),
                ];
            });

        $recentReturns = SaleReturn::whereIn('company_id', $companyIds)
            ->latest('return_date')
            ->limit(5)
            ->get()
            ->map(function ($ret) use ($branchNames) {
                return [
                    'branch' => $branchNames[$ret->company_id] ?? '-',
                    'type' => 'Return',
                    'number' => $ret->return_voucher_no,
                    'customer' => '-',
                    'date' => $ret->return_date,
                    'amount' => (float) $ret->return_total,
                ];
            });

        $recentApprovals = ApprovalHeader::with('customer')
            ->whereIn('company_id', $companyIds)
            ->latest('approval_date')
            ->limit(5)
            ->get()
            ->map(function ($approval) use ($branchNames) {
                return [
                    'branch' => $branchNames[$approval->company_id] ?? '-',
                    'type' => 'Approval',
                    'number' => $approval->approval_no,
                    'customer' => optional($approval->customer)->name ?: '-',
                    'date' => $approval->approval_date,
                    'amount' => (float) $approval->items()->sum('total_amount'),
                ];
            });

        $recentActivity = (new Collection())
            ->merge($recentSales)
            ->merge($recentReturns)
            ->merge($recentApprovals)
            ->sortByDesc(function ($row) {
                return strtotime((string) $row['date']);
            })
            ->take(8)
            ->values();

        return view('company.dashboard', [
            'canViewDashboardData' => true,
            'dashboardBranches' => $dashboardBranches, 'dashboardBranch' => $dashboardBranch, 'canManageBranches' => $canManageBranches,
            'users' => $usersCount,
            'customersCount' => $customersCount,
            'activeCustomersCount' => $activeCustomersCount,
            'salesToday' => $salesToday,
            'salesMonth' => $salesMonth,
            'returnsMonth' => $returnsMonth,
            'netMonth' => $salesMonth - $returnsMonth,
            'approvalsOpen' => $approvalsOpen,
            'pendingApprovalItems' => $pendingApprovalItems,
            'labelsInStock' => $labelsInStock,
            'monthlyLabels' => $monthlyLabels,
            'monthlySales' => $monthlySales,
            'monthlyReturns' => $monthlyReturns,
            'recentActivity' => $recentActivity,
            'metalRates' => $this->emptyMetalRates(),
        ]);
    }

    public function metalRatesJson()
    {
        $user = auth()->user();
        $canViewDashboardData = $user->hasRole('company_admin') || $user->can('dashboard-view');

        abort_unless($canViewDashboardData, 403);

        return response()
            ->json($this->metalRates())
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    private function metalRates(): array
    {
        $serviceClass = LiveMetalRateService::class;
        if (!class_exists($serviceClass)) {
            $servicePath = app_path('Services/LiveMetalRateService.php');
            if (is_file($servicePath)) {
                require_once $servicePath;
            }
        }

        return app($serviceClass)->rates();
    }

    private function emptyMetalRates(): array
    {
        return [
            'enabled' => false,
            'message' => 'Loading live metal rates...',
            'source' => 'JK Sons',
            'rates' => [],
            'updated_at' => null,
        ];
    }
}
