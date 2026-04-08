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
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CompanyDashboardController extends Controller
{
    public function index()
    {
        $companyId = auth()->user()->company_id;
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $usersCount = User::where('company_id', $companyId)->count();
        $customersCount = Customer::where('company_id', $companyId)->count();
        $activeCustomersCount = Customer::where('company_id', $companyId)->where('is_active', 1)->count();

        $salesToday = (float) Sale::where('company_id', $companyId)
            ->whereDate('sale_date', $today)
            ->sum('net_total');

        $salesMonth = (float) Sale::where('company_id', $companyId)
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('net_total');

        $returnsMonth = (float) SaleReturn::where('company_id', $companyId)
            ->whereBetween('return_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('return_total');

        $approvalsOpen = ApprovalHeader::where('company_id', $companyId)
            ->whereIn('status', ['open', 'partial'])
            ->count();

        $pendingApprovalItems = ApprovalItem::whereHas('approval', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->where('status', 'pending')->count();

        $labelsInStock = ItemSet::where('company_id', $companyId)
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
            $monthlySales[] = (float) Sale::where('company_id', $companyId)
                ->whereBetween('sale_date', [$start, $end])
                ->sum('net_total');
            $monthlyReturns[] = (float) SaleReturn::where('company_id', $companyId)
                ->whereBetween('return_date', [$start, $end])
                ->sum('return_total');
        }

        $recentSales = Sale::with('customer')
            ->where('company_id', $companyId)
            ->latest('sale_date')
            ->limit(5)
            ->get()
            ->map(function ($sale) {
                return [
                    'type' => 'Sale',
                    'number' => $sale->voucher_no,
                    'customer' => optional($sale->customer)->name ?: '-',
                    'date' => $sale->sale_date,
                    'amount' => (float) ($sale->net_total ?? $sale->total_amount ?? 0),
                ];
            });

        $recentReturns = SaleReturn::where('company_id', $companyId)
            ->latest('return_date')
            ->limit(5)
            ->get()
            ->map(function ($ret) {
                return [
                    'type' => 'Return',
                    'number' => $ret->return_voucher_no,
                    'customer' => '-',
                    'date' => $ret->return_date,
                    'amount' => (float) $ret->return_total,
                ];
            });

        $recentApprovals = ApprovalHeader::with('customer')
            ->where('company_id', $companyId)
            ->latest('approval_date')
            ->limit(5)
            ->get()
            ->map(function ($approval) {
                return [
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
        ]);
    }
}
