<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CompanyAuthController extends Controller
{

    public function show($slug)
    {

        $company = Company::where('slug', $slug)->first();

        if (!$company || (int) $company->status !== 1) {
            return redirect('/')
                ->with('company_unavailable', 'This company workspace is not available or has been removed.');
        }

        $pending = Auth::user();
        if ($pending && (!$pending->two_factor_enabled || session('company_2fa_verified') !== true)) {
            if ($pending->active_web_session_id === session()->getId()) {
                $pending->forceFill(['active_web_session_id'=>null])->save();
            }
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
        }

        return response()->view('company.login-company', compact('company'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function login(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->first();

        if (!$company || (int) $company->status !== 1) {
            return redirect('/')
                ->with('company_unavailable', 'This company workspace is not available or has been removed.');
        }

        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])
            ->where('company_id', $company->id)
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'email' => 'Invalid login credentials.',
            ])->withInput($request->only('email'));
        }

        if ((int) $user->is_active !== 1) {
            return back()->withErrors([
                'email' => 'Please contact admin. Your account is inactive.',
            ])->withInput($request->only('email'));
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('company_2fa_secret');
        $request->session()->put('company_2fa_verified', false);

        $user->forceFill([
            'active_web_session_id' => $request->session()->getId(),
            'active_login_at' => now(),
        ])->save();

        if (!$user->two_factor_enabled) {
            return redirect()
                ->route('company.2fa.setup', $user->company->slug);
        }

        return redirect()
            ->route('company.2fa.challenge', $user->company->slug);
    }

    public function logout(Request $request)
    {
        $user = auth()->user();
        $sessionId = $request->session()->getId();

        if ($user && $user->active_web_session_id === $sessionId) {
            $user->forceFill([
                'active_web_session_id' => null,
            ])->save();
        }

        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user && $user->company) {
            return redirect()
                ->route('company.login', $user->company->slug)
                ->with('success', 'Logged out successfully.');
        }

        return redirect('/')
            ->with('success', 'Logged out successfully.');
    }
}
