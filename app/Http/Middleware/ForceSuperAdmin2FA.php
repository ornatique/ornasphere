<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceSuperAdmin2FA
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('superadmin')->user();

        if (!$user) {
            return $next($request);
        }

        // allow challenge page
        if ($request->is('two-factor-challenge')) {
            return $next($request);
        }

        // if confirmed → skip setup → go dashboard
        if ($request->is('superadmin/2fa-setup') && $user->two_factor_confirmed_at) {
            return redirect()->route('superadmin.dashboard');
        }

        // not enabled → setup
        if (!$user->two_factor_secret || !$user->two_factor_confirmed_at) {
            return redirect()->route('superadmin.2fa.setup');
        }

        return $next($request);
    }
}
