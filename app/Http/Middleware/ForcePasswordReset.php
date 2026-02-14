<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForcePasswordReset
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if ($user && !$user->password_changed) {
            return redirect()
                ->route('password.set.form')
                ->withErrors([
                    'password' => 'You must set your password before continuing.',
                ]);
        }

        return $next($request);
    }
}
