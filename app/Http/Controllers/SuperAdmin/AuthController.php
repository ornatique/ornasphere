<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('superadmin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('superadmin')->attempt(
            $request->only('email', 'password')
        )) {
            $request->session()->regenerate();

            $user = Auth::guard('superadmin')->user();

            // NOT enabled → setup QR
            if (!$user->two_factor_secret || !$user->two_factor_confirmed_at) {
                return redirect()->route('superadmin.2fa.setup');
            }

            /*
        |--------------------------------------------------------------------------
        | FORCE OTP EVERY LOGIN (IMPORTANT)
        |--------------------------------------------------------------------------
        */
            session([
                'login.id' => $user->id,
                'login.remember' => false,
            ]);

            Auth::guard('superadmin')->logout();

            return redirect('/two-factor-challenge');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials',
        ]);
    }


    public function logout(Request $request)
    {
        Auth::guard('superadmin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('superadmin.login');
    }
}
