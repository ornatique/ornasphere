<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when not authenticated.
     */
    protected function redirectTo($request)
    {
        if ($request->expectsJson()) {
            return null;
        }

        // ✅ Company routes (slug based)
        if ($request->route('slug')) {
            return route('company.login', $request->route('slug'));
        }

        // ✅ Superadmin routes
        if ($request->is('superadmin/*')) {
            return route('superadmin.login');
        }

        // ❌ Never fallback to /login (Fortify)
        abort(403);
    }
}
