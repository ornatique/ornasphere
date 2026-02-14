<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;
use App\Mail\CompanyLoginMail;
use Illuminate\Support\Facades\DB;

class PasswordSetController extends Controller
{
    public function showForm(string $token)
    {
        $record = DB::table('password_set_tokens')
            ->where('token', $token)
            ->first();

        /**
         * 🔹 Token not found at all
         */
        if (!$record) {
            return redirect('/')
                ->with('success', 'Password already set. Please login.');
        }

        $user = \App\Models\User::with('company')->find($record->user_id);

        /**
         * 🔹 Token already used
         */
        if ($record->used_at) {
            return redirect()
                ->route('company.login', $user->company->slug)
                ->with('success', 'Password already set. Please login.');
        }

        /**
         * 🔹 Token expired
         */
        if (now()->greaterThan($record->expires_at)) {
            return redirect()
                ->route('company.login', $user->company->slug)
                ->withErrors('This password setup link has expired.');
        }

        /**
         * ✅ Valid token
         */
        return view('superadmin.auth.company.set-password', compact('token'));
    }


    public function update(Request $request, $token)
    {
        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        $record = DB::table('password_set_tokens')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) {
            return redirect()->route('company.login')
                ->withErrors('Invalid or expired token.');
        }

        $user = User::findOrFail($record->user_id);

        $user->update([
            'password' => Hash::make($request->password),
            'password_changed' => true, // 🔥 THIS IS KEY
            'password_set_at' => now(),
        ]);

        if ($user->company) {
            $user->company->update([
                'status' => 1, // ✅ ACTIVE
            ]);
        }


        DB::table('password_set_tokens')
            ->where('token', $token)
            ->update([
                'used_at' => now(),
            ]);

        return redirect()
            ->route('company.login', $user->company->slug)
            ->with('success', 'Password set successfully. Please login.');
    }
}
