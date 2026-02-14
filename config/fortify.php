<?php

use Laravel\Fortify\Features;

return [

    'guard' => 'superadmin',

    'passwords' => 'super_admins',

    'username' => 'email',

    'home' => '/superadmin/dashboard',

    'features' => [
        Features::twoFactorAuthentication([
            'confirm' => true,
        ]),
    ],

];
