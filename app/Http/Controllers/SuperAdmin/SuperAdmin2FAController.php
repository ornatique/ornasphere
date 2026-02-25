<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SuperAdmin;
use PragmaRX\Google2FA\Google2FA;

class SuperAdmin2FAController extends Controller
{
    public function show()
    {
        if (!session()->has('superadmin_2fa_id')) {
            return redirect()->route('superadmin.login');
        }

        return view('superadmin.auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required',
        ]);

        $user = SuperAdmin::find(session('superadmin_2fa_id'));

        if (!$user) {
            return redirect()->route('superadmin.login');
        }

        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey(
            $user->two_factor_secret,
            $request->code
        );

        if (!$valid) {
            return back()->withErrors([
                'code' => 'Invalid OTP',
            ]);
        }

        session()->forget('superadmin_2fa_id');

        Auth::guard('superadmin')->login($user);

        return redirect()->route('superadmin.dashboard');
    }

    public function setup()
    {
        $user = auth('superadmin')->user();

        $google2fa = new Google2FA();

        $secret = $google2fa->generateSecretKey();

        $user->two_factor_secret = $secret;
        $user->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return view('superadmin.auth.twofactor-setup', [
            'qrCodeUrl' => $qrCodeUrl,
            'secret' => $secret,
            'user' => $user
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required'
        ]);

        $user = auth('superadmin')->user();

        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey(
            $user->two_factor_secret,
            $request->code
        );

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid OTP']);
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        return redirect()->route('superadmin.dashboard');
    }
}
