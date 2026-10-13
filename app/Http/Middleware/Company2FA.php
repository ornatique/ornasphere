<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Company2FA
{
    public function handle($request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        if (!$user->two_factor_enabled) {
            return redirect()->route('company.2fa.setup', $user->company->slug);
        }

        if ($request->session()->get('company_2fa_verified') !== true) {
            return redirect()->route('company.2fa.challenge', $user->company->slug);
        }

        return $next($request);
    }
}
