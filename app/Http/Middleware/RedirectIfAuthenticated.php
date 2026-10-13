<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
       
        // 🔐 COMPANY USER
        if ($request->isMethod('GET') && Auth::check() && Route::is('company.login')) {
            $user = Auth::user();
            $slug = $user->company?->slug;

            if ($slug) {
                if (!$user->two_factor_enabled || $request->session()->get('company_2fa_verified') !== true) {
                    // Let the login page cancel unfinished verification so another account can sign in.
                    return $next($request);
                }
                return redirect()->route('company.dashboard', $slug);
            }
        }

        // 🔐 SUPERADMIN
        if (Auth::guard('superadmin')->check()) {
            return redirect()->route('superadmin.dashboard');
        }

        return $next($request);
    }
}
