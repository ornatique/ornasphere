<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdminIPWhitelist
{
    public function handle(Request $request, Closure $next)
    {
        $allowedIps = [
            '127.0.0.1',
            '::1',
            // add office/static IPs here
        ];

        if (! in_array($request->ip(), $allowedIps)) {
            abort(403, 'Access denied from this IP');
        }

        return $next($request);
    }
}
