<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdmin\AuthController as SuperAdminAuthController;
use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Controllers\Company\CompanyAuthController;
use App\Http\Controllers\Company\CompanyDashboardController;
use App\Http\Controllers\Company\PasswordSetController;
use App\Http\Controllers\Company\CompanySecurityController;
use App\Http\Controllers\Company\CompanyUserController;
use App\Http\Controllers\Company\CompanyRoleController;
use App\Http\Controllers\Company\CompanyPermissionController;
use App\Http\Controllers\Company\ItemController;
use App\Http\Controllers\Company\LabelConfigController;
use App\Http\Controllers\Company\LabelPrintController;
use App\Http\Controllers\Company\OtherChargeController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Super Admin Authentication (PUBLIC)
|--------------------------------------------------------------------------
*/
Route::prefix('superadmin')->name('superadmin.')->group(function () {

    Route::get('/login', [SuperAdminAuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [SuperAdminAuthController::class, 'login'])
        ->name('login.store');

    Route::post('/logout', [SuperAdminAuthController::class, 'logout'])
        ->name('logout');
});

/*
|--------------------------------------------------------------------------
| Super Admin Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:superadmin',
    'superadmin.ip',
    'superadmin.2fa',
])->prefix('superadmin')->name('superadmin.')->group(function () {

    Route::get('/dashboard', function () {
        return view('superadmin.auth.dashboard');
    })->name('dashboard');

    Route::resource('companies', CompanyController::class);

    Route::post(
        'companies/{company}/toggle-status',
        [CompanyController::class, 'toggleStatus']
    )->name('companies.toggle-status');

    Route::post(
        'companies/{company}/resend-login',
        [CompanyController::class, 'resendLogin']
    )->name('companies.resend');

    Route::post(
        'companies/{company}/reset-2fa',
        [CompanyController::class, 'reset2fa']
    )->name('companies.reset-2fa');
});

/*
|--------------------------------------------------------------------------
| Company Authentication (PUBLIC, slug-based)
|--------------------------------------------------------------------------
*/

Route::prefix('company/{slug}')
    ->name('company.')
    ->middleware('guest')
    ->group(function () {

        Route::get('/login', [CompanyAuthController::class, 'show'])
            ->name('login');

        Route::post('/login', [CompanyAuthController::class, 'login'])
            ->name('login.post');

        Route::post('logout', [CompanyAuthController::class, 'logout'])
            ->name('logout');
    });

/*
|--------------------------------------------------------------------------
| Company Protected Routes (AFTER LOGIN)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'company.2fa'])
    ->prefix('company/{slug}')
    ->name('company.')
    ->group(function () {

        Route::get('/dashboard', [CompanyDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/users', [CompanyUserController::class, 'index'])
            ->name('users.index')
        ;

        Route::get('/users/create', [CompanyUserController::class, 'create'])
            ->name('users.create')
        ;

        Route::post('/users', [CompanyUserController::class, 'store'])
            ->name('users.store')
        ;

        Route::get('/users', [CompanyUserController::class, 'index'])
            ->name('users.index');

        Route::get('/users/create', [CompanyUserController::class, 'create'])
            ->name('users.create');

        Route::post('/users', [CompanyUserController::class, 'store'])
            ->name('users.store');

        Route::get('/users/{encryptedId}/edit', [CompanyUserController::class, 'edit'])
            ->name('users.edit');

        Route::put('/users/{encryptedId}', [CompanyUserController::class, 'update'])
            ->name('users.update');

        Route::delete('/users/{encryptedId}', [CompanyUserController::class, 'destroy'])
            ->name('users.delete');

        Route::post(
            '/users/{encryptedId}/toggle',
            [CompanyUserController::class, 'toggle']
        )
            ->name('users.toggle');

        Route::get(
            '/check-employee-limit',
            [CompanyUserController::class, 'checkEmployeeLimit']
        )->name('check.employee.limit');

        // ================= ITEMS =================

        Route::get('/items', [ItemController::class, 'index'])
            ->name('items.index');

        Route::get('/items/create', [ItemController::class, 'create'])
            ->name('items.create');

        Route::post('/items', [ItemController::class, 'store'])
            ->name('items.store');

        Route::get('/items/{encryptedId}/edit', [ItemController::class, 'edit'])
            ->name('items.edit');

        Route::put('/items/{encryptedId}', [ItemController::class, 'update'])
            ->name('items.update');

        Route::delete('/items/{encryptedId}', [ItemController::class, 'destroy'])
            ->name('items.destroy');

        Route::get('/label-config', [LabelConfigController::class, 'index'])
            ->name('label_config.index');

        Route::get('/label-config/create', [LabelConfigController::class, 'create'])
            ->name('label_config.create');

        Route::post('/label-config/store', [LabelConfigController::class, 'store'])
            ->name('label_config.store');

        Route::get('/label-config/{encryptedId}/edit', [LabelConfigController::class, 'edit'])
            ->name('label_config.edit');

        Route::put('/label-config/{encryptedId}/update', [LabelConfigController::class, 'update'])
            ->name('label_config.update');

        Route::delete('/label-config/{encryptedId}/delete', [LabelConfigController::class, 'destroy'])
            ->name('label_config.delete');

        // Route::get(
        //     '/label-config/qr/{prefix}',
        //     [LabelConfigController::class, 'generateQR']
        // )
        //     ->name('label-config.qr');
        Route::get('/label-print', [LabelPrintController::class, 'index'])
            ->name('label.print');

        Route::post('/label-generate', [LabelPrintController::class, 'generate'])
            ->name('label.generate');

        Route::get('/label-print/qr/{encryptedId}', [LabelPrintController::class, 'qrImage'])
            ->name('label.print.qr');

        Route::get('other-charge', [OtherChargeController::class, 'index'])
            ->name('other-charge.index');

        Route::get('other-charge/create', [OtherChargeController::class, 'create'])
            ->name('other-charge.create');

        Route::post('other-charge/store', [OtherChargeController::class, 'store'])
            ->name('other-charge.store');

        Route::get('other-charge/edit/{id}', [OtherChargeController::class, 'edit'])
            ->name('other-charge.edit');

        Route::post('other-charge/update/{id}', [OtherChargeController::class, 'update'])
            ->name('other-charge.update');

        // ================= YAJRA DATATABLE =================
        Route::get('/items-data', [ItemController::class, 'data'])
            ->name('items.data');
        Route::get('roles', [CompanyRoleController::class, 'index'])
            ->name('roles.index');

        Route::get('roles/create', [CompanyRoleController::class, 'create'])
            ->name('roles.create');

        Route::post('roles', [CompanyRoleController::class, 'store'])
            ->name('roles.store');

        Route::get('roles/{id}/edit', [CompanyRoleController::class, 'edit'])
            ->name('roles.edit');

        Route::put('roles/{id}', [CompanyRoleController::class, 'update'])
            ->name('roles.update');

        Route::delete('roles/{id}', [CompanyRoleController::class, 'destroy'])
            ->name('roles.delete');

        Route::get('permissions', [CompanyPermissionController::class, 'index'])
            ->name('permissions.index');

        Route::get('permissions/create', [CompanyPermissionController::class, 'create'])
            ->name('permissions.create');

        Route::post('permissions', [CompanyPermissionController::class, 'store'])
            ->name('permissions.store');

        Route::get('permissions/{permission}/edit', [CompanyPermissionController::class, 'edit'])
            ->name('permissions.edit');

        Route::put('permissions/{permission}', [CompanyPermissionController::class, 'update'])
            ->name('permissions.update');

        Route::delete('permissions/{permission}', [CompanyPermissionController::class, 'destroy'])
            ->name('permissions.destroy');
    });
Route::prefix('company/{slug}')
    ->name('company.')
    ->group(function () {

        Route::get('/2fa/setup', [CompanySecurityController::class, 'showSetup'])
            ->name('2fa.setup');

        Route::post('/2fa/verify-setup', [CompanySecurityController::class, 'verifySetup'])
            ->name('2fa.verify.setup');

        Route::get('/2fa/challenge', [CompanySecurityController::class, 'challenge'])
            ->name('2fa.challenge');

        Route::post('/2fa/verify', [CompanySecurityController::class, 'verify'])
            ->name('2fa.verify');
    });


/*
|--------------------------------------------------------------------------
| Password Setup / Reset (PUBLIC, Token Based)
|--------------------------------------------------------------------------
*/

Route::get('/set-password/{token}', [PasswordSetController::class, 'showForm'])
    ->name('password.set.form');

Route::post('/set-password/{token}', [PasswordSetController::class, 'update'])
    ->name('password.set.update');


Route::post('/company/2fa/enable', [CompanySecurityController::class, 'enable']);
Route::post('/company/2fa/disable', [CompanySecurityController::class, 'disable']);
