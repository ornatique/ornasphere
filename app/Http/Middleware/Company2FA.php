<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Company2FA
{
    public function handle($request, Closure $next)
    {
        $user = auth()->user();

        if ($user->two_factor_enabled && !session('company_2fa_verified')) {
            return redirect()->route('company.2fa.setup', $request->slug);
        }

        return $next($request);
    }
}
