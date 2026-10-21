<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->company_id) {
            return $next($request);
        }

        $company = $user->company;
        if ($request->route('slug')) {
            abort_unless($company && $company->slug === (string) $request->route('slug'), 403);
        }
        $parentInactive = false;
        if ($company && $company->parent_company_id) {
            $parent = \App\Models\Company::find($company->parent_company_id);
            $parentInactive = !$parent || (int) $parent->status !== 1;
        }

        if ((int) $user->is_active !== 1) {
            if ($request->expectsJson() || $request->is('api/*')) {
                if ($user->currentAccessToken()) {
                    \App\Services\InactiveTokenResponse::revoke($user, $company && ((int) $company->status !== 1 || $parentInactive) ? 'COMPANY_INACTIVE' : 'USER_INACTIVE');
                }

                return response()->json([
                    'success' => false,
                    'code' => 'USER_INACTIVE',
                    'message' => 'Your account has been deactivated. Please contact your company admin.',
                ], 403);
            }

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($company && $company->slug) {
                return redirect()
                    ->route('company.login', $company->slug)
                    ->withErrors(['email' => 'Your account has been deactivated. Please contact your company admin.']);
            }

            return redirect('/')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact your company admin.']);
        }

        if (!$company || (int) $company->status !== 1 || $parentInactive) {
            // Revoke current API token if present.
            if ($request->expectsJson() || $request->is('api/*')) {
                if ($user->currentAccessToken()) {
                    \App\Services\InactiveTokenResponse::revoke($user);
                }

                return response()->json([
                    'success' => false,
                    'code' => 'COMPANY_INACTIVE',
                    'message' => 'Company inactive. Contact administrator.',
                ], 403);
            }

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($company && $company->slug) {
                return redirect()
                    ->route('company.login', $company->slug)
                    ->withErrors(['email' => 'Company is inactive. Please contact super admin.']);
            }

            return redirect('/')
                ->withErrors(['email' => 'Company is inactive. Please contact super admin.']);
        }

        return $next($request);
    }
}
