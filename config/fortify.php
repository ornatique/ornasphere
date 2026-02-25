<?php

use Laravel\Fortify\Features;

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'home' => '/dashboard',

    'views' => false,

    'features' => [
        Features::twoFactorAuthentication([
            'confirm' => true,
        ]),
    ],
];
