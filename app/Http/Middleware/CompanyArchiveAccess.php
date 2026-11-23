<?php

namespace App\Http\Middleware;

use App\Services\CompanyArchiveGate;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyArchiveAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (!CompanyArchiveGate::ready()) return $next($request);
        // Archive actions run their own exclusive state transitions and must not count themselves.
        if ($request->routeIs('company.profile.archive.*', 'api.company.archive.*')) return $next($request);
        $ids = [];
        $user = $request->user();
        if (!$user && $request->bearerToken()) $user = $request->user('sanctum');
        if ($user?->company_id) $ids[] = (int) $user->company_id;
        $slug = $request->route('slug') ?: $request->route('companySlug');
        if (is_string($slug)) $ids[] = (int) DB::table('companies')->where('slug', $slug)->value('id');
        foreach (['company', 'branch'] as $parameter) {
            $routeCompany = $request->route($parameter);
            if ($routeCompany) $ids[] = (int) (is_object($routeCompany) ? $routeCompany->id : $routeCompany);
        }
        foreach (['company_id', 'management_branches'] as $key) foreach ((array) $request->input($key, []) as $id) {
            if (is_scalar($id) && ctype_digit((string) $id)) $ids[] = (int) $id;
        }
        $ids = DB::table('companies')->whereIn('id', array_unique(array_filter($ids)))->pluck('id')->map(fn ($id) => (int)$id)->all(); sort($ids);
        $leases = []; $readOnly=false; $deferRelease=false;
        $release = CompanyArchiveGate::protectRequests($leases);
        try {
            foreach ($ids as $id) {
                $state = CompanyArchiveGate::state($id);
                if (!config('company_archive.enabled') && (!$state || $state->status === 'active')) continue;
                // Retained account authentication and an admin's read-only Profile remain available.
                $accountRoute = $request->routeIs('company.login*', 'company.logout', 'company.2fa.*', 'api.company.login', 'api.company.verify-otp', 'api.company.logout')
                    || ($request->routeIs('company.profile.show') && $user?->hasRole('company_admin'));
                if ($state && $state->status !== 'active' && $accountRoute) {
                    if ($request->routeIs('company.profile.show')) $readOnly=true;
                    continue;
                }
                if (!CompanyArchiveGate::enter($id)) {
                    if ($this->isSafeRead($request) && CompanyArchiveGate::enterRead($id)) {
                        $leases[]=$id; $readOnly=true; continue;
                    }
                    if ($request->expectsJson() || $request->is('api/*')) return response()->json([
                        'success' => false, 'code' => 'COMPANY_ARCHIVED',
                        'message' => 'This company is in archive mode. Viewing is allowed when the archive is idle; adding, editing and deleting are disabled. Use Company Profile to restore or cancel.',
                    ], 423);
                    if ($user && (int) $user->company_id === $id && $user->hasRole('company_admin')) {
                        return redirect()->route('company.profile.show', $user->company->slug)->with('archive_notice', 'Company entries are paused. Use Archive / Restore below.');
                    }
                    return response('This company is paused for archive/restore. Contact its administrator.', 423);
                }
                $leases[] = $id;
            }
            if (!$readOnly) return $next($request);
            $request->attributes->set('company_archive_read_only',true);
            $response=$this->rollbackOnly(fn ()=>$next($request));
            $response->headers->set('X-Company-Archive-Read-Only','1');
            if ($response instanceof StreamedResponse) {
                $callback=$response->getCallback(); $deferRelease=true;
                $response->setCallback(function () use ($callback,$release) {
                    try { $this->rollbackOnly(fn ()=>$callback()); }
                    finally { $release(); }
                });
            }
            return $response;
        } finally { if (!$deferRelease) $release(); }
    }

    private function isSafeRead(Request $request): bool
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) return false;
        $action=$request->route()?->getAction('uses') ?? '';
        $method=is_string($action) && str_contains($action,'@') ? substr($action,strrpos($action,'@')+1) : '';
        // Legacy GET actions can print/update stock, dispatch processing or delete files.
        if (preg_match('/edit|create|store|update|destroy|delete|reset|toggle|activate|finalize|generate|reserve|printDirect|printPdf|printLabels|upload|process|applyGroup|approve|reject|enable|disable/i',$method)) return false;
        if (preg_match('~/(create|edit|delete|destroy|finalize|generate|reserve|upload|process)(/|$)~i',$request->path())) return false;
        return true;
    }

    private function rollbackOnly(callable $read): mixed
    {
        // Some existing list/detail pages auto-create defaults or mark notifications read.
        // Render them normally, but discard all default-connection DB changes, including nested transactions.
        $connection=DB::connection(); $level=$connection->transactionLevel(); $connection->beginTransaction();
        try { return $read(); }
        finally { while ($connection->transactionLevel()>$level) $connection->rollBack(); }
    }
}
