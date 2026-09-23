<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleActiveWebSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('superadmin')->check()) {
            $superAdmin = Auth::guard('superadmin')->user();

            if ($this->isReplacedSession($superAdmin?->active_web_session_id, $request->session()->getId())) {
                Auth::guard('superadmin')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('superadmin.login')
                    ->with('error', 'Your account was logged in on another device. Please login again.');
            }
        }

        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();

            if ($this->isReplacedSession($user?->active_web_session_id, $request->session()->getId())) {
                $slug = (string) ($request->route('slug') ?: optional($user->company)->slug);

                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($slug !== '') {
                    return redirect()
                        ->route('company.login', $slug)
                        ->with('error', 'Your account was logged in on another device. Please login again.');
                }

                return redirect('/')
                    ->with('error', 'Your account was logged in on another device. Please login again.');
            }
        }

        return $next($request);
    }

    private function isReplacedSession(?string $activeSessionId, string $currentSessionId): bool
    {
        return $activeSessionId !== null
            && $activeSessionId !== ''
            && !hash_equals($activeSessionId, $currentSessionId);
    }
}
