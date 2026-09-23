<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class SuperAdmin extends Authenticatable
{
    use TwoFactorAuthenticatable;
    protected $fillable = [
        'name',
        'email',
        'password',
        'active_web_session_id',
        'active_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'active_login_at' => 'datetime',
    ];
}
