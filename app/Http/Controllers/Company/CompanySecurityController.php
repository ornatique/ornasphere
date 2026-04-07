<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Str;

class CompanySecurityController extends Controller
{
    /**
     * FIRST TIME ONLY – QR SETUP
     */
    public function showSetup($slug)
    {
        $user = Auth::user();
            
        if ($user->two_factor_enabled) {
            return redirect()->route('company.2fa.challenge', $slug);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey(32);

        session(['company_2fa_secret' => $secret]);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return view('company.2fa-setup', compact('qrCodeUrl', 'slug'));
    }

    /**
     * OTP VERIFY (USED FOR BOTH SETUP & LOGIN)
     */
    public function verify(Request $request, $slug)
    {
        $request->validate([
            'otp' => 'required|digits:6',
        ]);

        $google2fa = new Google2FA();
        $user = Auth::user();

        // FIRST TIME SETUP
        if (!$user->two_factor_enabled) {

            $secret = session('company_2fa_secret');

            if (!$secret) {
                return redirect()->route('company.login', $slug);
            }

            if (!$google2fa->verifyKey($secret, $request->otp)) {
                return back()->withErrors(['otp' => 'Invalid OTP']);
            }

            $recoveryCodes = collect(range(1, 8))
                ->map(fn () => strtoupper(Str::random(10)))
                ->toArray();

            $user->update([
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => json_encode($recoveryCodes),
                'two_factor_enabled' => 1,
            ]);

            session()->forget('company_2fa_secret');
        }

        // NORMAL LOGIN VERIFY
        else {
            if (!$google2fa->verifyKey($user->two_factor_secret, $request->otp)) {
                return back()->withErrors(['otp' => 'Invalid OTP']);
            }
        }

        session(['company_2fa_verified' => true]);

        return redirect()->route('company.dashboard', $slug);
    }

    /**
     * Backward-compatible endpoint used by existing routes.
     */
    public function verifySetup(Request $request, $slug)
    {
        return $this->verify($request, $slug);
    }

    /**
     * OTP ONLY PAGE (SECOND LOGIN+)
     */
    public function challenge($slug)
    {
        return view('company.2fa-challenge', [
            'slug' => $slug,
            'user' => Auth::user(),
        ]);
    }

    public function enable(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $user->update(['two_factor_enabled' => 1]);
        return response()->json(['success' => true, 'message' => '2FA enabled']);
    }

    public function disable(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $user->update([
            'two_factor_enabled' => 0,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        return response()->json(['success' => true, 'message' => '2FA disabled']);
    }
}
