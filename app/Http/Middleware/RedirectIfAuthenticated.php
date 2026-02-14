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
        if (Auth::check() && Route::is('company.login')) {
            $slug = $request->route('slug');

            if ($slug) {
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
