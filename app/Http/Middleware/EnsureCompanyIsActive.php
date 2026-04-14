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

        if (!$company || (int) $company->status !== 1) {
            // Revoke current API token if present.
            if ($request->expectsJson() || $request->is('api/*')) {
                if ($user->currentAccessToken()) {
                    $user->currentAccessToken()->delete();
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
