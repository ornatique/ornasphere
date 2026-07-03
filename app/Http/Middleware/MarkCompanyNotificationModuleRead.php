<?php

namespace App\Http\Middleware;

use App\Services\CompanyNotificationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarkCompanyNotificationModuleRead
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->boolean('mark_notifications_read')) {
            $modules = CompanyNotificationService::modulesForRoute(optional($request->route())->getName());
            if ($modules) {
                CompanyNotificationService::markModuleRead($request->user(), $modules);
            }
        }

        return $next($request);
    }
}
