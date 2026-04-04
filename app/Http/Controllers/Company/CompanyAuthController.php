<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CompanyAuthController extends Controller
{

    public function show($slug)
    {

        $company = Company::where('slug', $slug)->firstOrFail();
        return view('company.login-company', compact('company'));
    }

    public function login(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {

            $user = Auth::user();

            if ((int) $user->company_id !== (int) $company->id) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'This user does not belong to this company.',
                ]);
            }

            if ((int) $company->status !== 1) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Company is inactive. Please contact super admin.',
                ]);
            }

            if ($user->is_active != 1) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Please contact admin. Your account is inactive.',
                ]);
            }

            $request->session()->regenerate();

            if (!$user->two_factor_enabled) {
                return redirect()
                    ->route('company.2fa.setup', $user->company->slug);
            }

            session(['company_2fa_verified' => false]);

            return redirect()
                ->route('company.2fa.challenge', $user->company->slug);
        }

        return back()->withErrors([
            'email' => 'Invalid login credentials.',
        ]);
    }

    public function logout(Request $request)
    {
        $user = auth()->user();

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
