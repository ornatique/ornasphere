<?php

namespace App\Http\Middleware;

use App\Services\OfficeAccessGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckWorkerOfficeAccess
{
    public function handle(Request $request, Closure $next, OfficeAccessGuard $guard): Response
    {
        $user = $request->user();
        $decision = $user ? $guard->evaluate($request, $user) : ['allowed' => true];

        if (!$decision['allowed']) {
            if ($decision['should_log'] ?? true) {
                $guard->log($request, $user, $decision);
            }

            return $guard->blockedResponse($decision);
        }

        if ($decision['should_log'] ?? true) {
            $guard->log($request, $user, $decision);
        }

        return $next($request);
    }
}
