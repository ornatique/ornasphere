<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Auth;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // // Company login
        // Fortify::loginView(function () {
        //     return view('company.login-company');
        // });

        // Company 2FA challenge
        Fortify::twoFactorChallengeView(function () {
            return view('superadmin.auth.two-factor-challenge');
        });
    }
}
