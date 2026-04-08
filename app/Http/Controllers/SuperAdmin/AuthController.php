<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalHeader;
use App\Models\Company;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('superadmin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('superadmin')->attempt(
            $request->only('email', 'password')
        )) {

            $request->session()->regenerate();

            $user = Auth::guard('superadmin')->user();

            /*
            |--------------------------------------------------------------------------
            | IF 2FA NOT ENABLED → GO TO SETUP
            |--------------------------------------------------------------------------
            */
            if (!$user->two_factor_secret || !$user->two_factor_confirmed_at) {
                return redirect()->route('superadmin.dashboard');
            }

            /*
            |--------------------------------------------------------------------------
            | FORCE 2FA EVERY LOGIN
            |--------------------------------------------------------------------------
            */

            session([
                'superadmin_2fa_id' => $user->id,
            ]);

            Auth::guard('superadmin')->logout();

            return redirect()->route('superadmin.2fa.challenge');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('superadmin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('superadmin.login');
    }

    public function dashboard()
    {
        $totalCompanies = Company::count();
        $activeCompanies = Company::where('status', 1)->count();
        $inactiveCompanies = Company::where('status', 0)->count();
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', 1)->count();

        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();

        $monthlySales = (float) Sale::whereBetween('sale_date', [$monthStart, $monthEnd])->sum('net_total');
        $monthlyReturns = (float) SaleReturn::whereBetween('return_date', [$monthStart, $monthEnd])->sum('return_total');
        $openApprovals = ApprovalHeader::whereIn('status', ['open', 'partial'])->count();

        $labels = [];
        $companyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = Carbon::now()->subMonths($i);
            $labels[] = $m->format('M Y');
            $companyTrend[] = Company::whereBetween('created_at', [
                $m->copy()->startOfMonth()->toDateTimeString(),
                $m->copy()->endOfMonth()->toDateTimeString(),
            ])->count();
        }

        $recentCompanies = Company::withCount('users')
            ->latest()
            ->limit(8)
            ->get();

        return view('superadmin.auth.dashboard', compact(
            'totalCompanies',
            'activeCompanies',
            'inactiveCompanies',
            'totalUsers',
            'activeUsers',
            'monthlySales',
            'monthlyReturns',
            'openApprovals',
            'labels',
            'companyTrend',
            'recentCompanies'
        ));
    }
}
