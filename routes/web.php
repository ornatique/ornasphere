<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\SuperAdmin\AuthController as SuperAdminAuthController;
use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Controllers\Company\CompanyAuthController;
use App\Http\Controllers\Company\CompanyDashboardController;
use App\Http\Controllers\Company\PasswordSetController;
use App\Http\Controllers\Company\CompanySecurityController;
use App\Http\Controllers\Company\CompanyUserController;
use App\Http\Controllers\Company\CompanyRoleController;
use App\Http\Controllers\Company\CompanyPermissionController;
use App\Http\Controllers\Company\CustomerController;
use App\Http\Controllers\Company\JobWorkerController;
use App\Http\Controllers\Company\JobworkIssueController;
use App\Http\Controllers\Company\ItemController;
use App\Http\Controllers\Company\LabelConfigController;
use App\Http\Controllers\Company\LabelPrintController;
use App\Http\Controllers\Company\OtherChargeController;
use App\Http\Controllers\Company\ProductionCostController;
use App\Http\Controllers\Company\LabourFormulaController;
use App\Http\Controllers\Company\ProductionStepController;
use App\Http\Controllers\Company\VacuumBuchController;
use App\Http\Controllers\Company\VacuumLiveDashboardController;
use App\Http\Controllers\Company\VacuumProcessController;
use App\Http\Controllers\Company\VacuumVoucherController;
use App\Http\Controllers\Company\CastingHeatingController;
use App\Http\Controllers\Company\CastingMetalIssueController;
use App\Http\Controllers\Company\CastingReleaseController;
use App\Http\Controllers\Company\TreeCuttingIssueController;
use App\Http\Controllers\Company\TreeCuttingReceiveController;
use App\Http\Controllers\Company\CastingSortingController;
use App\Http\Controllers\Company\VoucherHistoryController;
use App\Http\Controllers\Company\ItemSetController;
use Endroid\QrCode\QrCode; 
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Writer\PngWriter;
use App\Http\Controllers\Company\SaleController;
use App\Http\Controllers\Company\SaleReturnController;
use App\Http\Controllers\Company\ReportController;
use App\Http\Controllers\Company\NotificationController;
use App\Http\Controllers\SuperAdmin\SuperAdmin2FAController;
use App\Http\Controllers\Company\ApprovalController;
use App\Http\Controllers\Company\CustomerAdvanceController;
use App\Http\Controllers\Company\AppThemeController;
use App\Http\Controllers\Api\VisitingCardApiController as VisitingCardApiWebController;



/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (Auth::guard('superadmin')->check()) {
        return redirect()->route('superadmin.dashboard');
    }

    if (Auth::check() && !empty(optional(Auth::user()->company)->slug)) {
        return redirect()->route('company.dashboard', ['slug' => Auth::user()->company->slug]);
    }

    return view('welcome');
});

Route::get('/app', function () {
    $androidUrl = 'https://play.google.com/store/apps/details?id=com.ornatique';
    $iosUrl = 'https://apps.apple.com/by/app/ornatique/id6746642350';
    $userAgent = strtolower(request()->userAgent() ?? '');

    if (str_contains($userAgent, 'android')) {
        return redirect()->away($androidUrl);
    }

    if (
        str_contains($userAgent, 'iphone') ||
        str_contains($userAgent, 'ipad') ||
        str_contains($userAgent, 'ipod')
    ) {
        return redirect()->away($iosUrl);
    }

    return view('app-download', compact('androidUrl', 'iosUrl'));
})->name('app.download');

Route::get('/app/qr', function () {
    $qr = new QrCode(
        data: url('/app'),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: 400,
        margin: 16
    );

    $logo = new Logo(
        path: public_path('images/ornatique-qr-logo-white-box.png'),
        resizeToWidth: 110,
        resizeToHeight: 110,
        punchoutBackground: false
    );

    $writer = new PngWriter();

    return response(
        $writer->write($qr, $logo)->getString(),
        200,
        ['Content-Type' => 'image/png']
    );
})->name('app.download.qr');

Route::get('/download-app', function () {
    $androidUrl = 'https://play.google.com/store/apps/details?id=com.ornatique';
    $iosUrl = 'https://apps.apple.com/by/app/ornatique/id6746642350';
    $userAgent = strtolower(request()->userAgent() ?? '');

    if (str_contains($userAgent, 'android')) {
        return redirect()->away($androidUrl);
    }

    if (
        str_contains($userAgent, 'iphone') ||
        str_contains($userAgent, 'ipad') ||
        str_contains($userAgent, 'ipod')
    ) {
        return redirect()->away($iosUrl);
    }

    return view('app-download', compact('androidUrl', 'iosUrl'));
})->name('download-app');

Route::get('/download-app/qr', function () {
    $qr = new QrCode(
        data: url('/download-app'),
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: 400,
        margin: 16
    );

    $logo = new Logo(
        path: public_path('images/ornatique-qr-logo-white-box.png'),
        resizeToWidth: 100,
        resizeToHeight: 100,
        punchoutBackground: false
    );

    $writer = new PngWriter();

    return response(
        $writer->write($qr, $logo)->getString(),
        200,
        ['Content-Type' => 'image/png']
    );
})->name('download-app.qr');

Route::get('/dashboard', function () {
    if (Auth::guard('superadmin')->check()) {
        return redirect()->route('superadmin.dashboard');
    }

    if (Auth::check() && !empty(optional(Auth::user()->company)->slug)) {
        return redirect()->route('company.dashboard', ['slug' => Auth::user()->company->slug]);
    }

    return redirect()->route('superadmin.login');
});

Route::get('/company', function () {
    if (Auth::check() && !empty(optional(Auth::user()->company)->slug)) {
        return redirect()->route('company.dashboard', ['slug' => Auth::user()->company->slug]);
    }

    if (Auth::guard('superadmin')->check()) {
        return redirect()->route('superadmin.dashboard');
    }

    return redirect('/');
});

Route::get('/company/{slug}', function (string $slug) {
    if (Auth::check()) {
        return redirect()->route('company.dashboard', ['slug' => $slug]);
    }

    return redirect()->route('company.login', ['slug' => $slug]);
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

    Route::get('/two-factor-challenge', [SuperAdmin2FAController::class, 'show'])
        ->name('2fa.challenge');

    Route::post('/two-factor-challenge', [SuperAdmin2FAController::class, 'verify'])
        ->name('2fa.verify');

    Route::get('/two-factor-setup', [SuperAdmin2FAController::class, 'setup'])
        ->name('2fa.setup');

    Route::post('/two-factor-setup', [SuperAdmin2FAController::class, 'store'])
        ->name('2fa.store');
});

/*
|--------------------------------------------------------------------------
| Super Admin Protected Routes
|--------------------------------------------------------------------------
*/
// 'superadmin.ip',
Route::middleware([
    'auth:superadmin',
    'superadmin.2fa',
])->prefix('superadmin')->name('superadmin.')->group(function () {

    Route::get('/dashboard', [SuperAdminAuthController::class, 'dashboard'])
        ->name('dashboard');

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
    });

/*
|--------------------------------------------------------------------------
| Company Protected Routes (AFTER LOGIN)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'company.active', 'company.2fa', 'company.route.permission', 'company.notification.read'])
    ->prefix('company/{slug}')
    ->name('company.')
    ->group(function () {

        Route::post('logout', [CompanyAuthController::class, 'logout'])
            ->name('logout');

        Route::get('notifications/summary', [NotificationController::class, 'summary'])
            ->name('notifications.summary');
        Route::get('notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::post('notifications/read', [NotificationController::class, 'markAllRead'])
            ->name('notifications.read');
        Route::post('notifications/read-module', [NotificationController::class, 'markModuleRead'])
            ->name('notifications.read-module');

        Route::get('/dashboard', [CompanyDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/app-themes', [AppThemeController::class, 'index'])
            ->name('app-themes.index');
        Route::get('/app-themes/create', [AppThemeController::class, 'create'])
            ->name('app-themes.create');
        Route::post('/app-themes', [AppThemeController::class, 'store'])
            ->name('app-themes.store');
        Route::get('/app-themes/{theme}/edit', [AppThemeController::class, 'edit'])
            ->name('app-themes.edit');
        Route::put('/app-themes/{theme}', [AppThemeController::class, 'update'])
            ->name('app-themes.update');
        Route::post('/app-themes/{theme}/activate', [AppThemeController::class, 'activate'])
            ->name('app-themes.activate');
        Route::delete('/app-themes/{theme}', [AppThemeController::class, 'destroy'])
            ->name('app-themes.destroy');

        Route::get('/users', [CompanyUserController::class, 'index'])
            ->name('users.index')
        ;

        Route::get('/customers', [CustomerController::class, 'index'])
            ->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])
            ->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])
            ->name('customers.store');
        Route::get('/customers/export/excel', [CustomerController::class, 'exportExcel'])
            ->name('customers.export.excel');
        Route::get('/customers/export/pdf', [CustomerController::class, 'exportPdf'])
            ->name('customers.export.pdf');
        Route::get('/customers/{encryptedId}/edit', [CustomerController::class, 'edit'])
            ->name('customers.edit');
        Route::put('/customers/{encryptedId}', [CustomerController::class, 'update'])
            ->name('customers.update');
        Route::delete('/customers/{encryptedId}', [CustomerController::class, 'destroy'])
            ->name('customers.delete');

        Route::get('/job-workers', [JobWorkerController::class, 'index'])
            ->name('job-workers.index');
        Route::get('/job-workers/create', [JobWorkerController::class, 'create'])
            ->name('job-workers.create');
        Route::post('/job-workers', [JobWorkerController::class, 'store'])
            ->name('job-workers.store');
        Route::get('/job-workers/export/excel', [JobWorkerController::class, 'exportExcel'])
            ->name('job-workers.export.excel');
        Route::get('/job-workers/export/pdf', [JobWorkerController::class, 'exportPdf'])
            ->name('job-workers.export.pdf');
        Route::get('/job-workers/{encryptedId}/edit', [JobWorkerController::class, 'edit'])
            ->name('job-workers.edit');
        Route::put('/job-workers/{encryptedId}', [JobWorkerController::class, 'update'])
            ->name('job-workers.update');
        Route::delete('/job-workers/{encryptedId}', [JobWorkerController::class, 'destroy'])
            ->name('job-workers.destroy');

        Route::get('/jobwork-issue', [JobworkIssueController::class, 'index'])
            ->name('jobwork-issue.index');
        Route::get('/jobwork-issue/create', [JobworkIssueController::class, 'create'])
            ->name('jobwork-issue.create');
        Route::post('/jobwork-issue', [JobworkIssueController::class, 'store'])
            ->name('jobwork-issue.store');
        Route::get('/jobwork-issue/export/excel', [JobworkIssueController::class, 'exportExcel'])
            ->name('jobwork-issue.export.excel');
        Route::get('/jobwork-issue/export/pdf', [JobworkIssueController::class, 'exportPdf'])
            ->name('jobwork-issue.export.pdf');
        Route::get('/jobwork-issue/{encryptedId}/export/excel', [JobworkIssueController::class, 'exportSingleExcel'])
            ->name('jobwork-issue.export-single.excel');
        Route::get('/jobwork-issue/{encryptedId}/export/pdf', [JobworkIssueController::class, 'exportSinglePdf'])
            ->name('jobwork-issue.export-single.pdf');
        Route::get('/jobwork-issue/{encryptedId}/view', [JobworkIssueController::class, 'show'])
            ->name('jobwork-issue.show');
        Route::get('/jobwork-issue/{encryptedId}/edit', [JobworkIssueController::class, 'edit'])
            ->name('jobwork-issue.edit');
        Route::put('/jobwork-issue/{encryptedId}', [JobworkIssueController::class, 'update'])
            ->name('jobwork-issue.update');
        Route::delete('/jobwork-issue/{encryptedId}', [JobworkIssueController::class, 'destroy'])
            ->name('jobwork-issue.destroy');

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

        Route::post(
            '/users/{encryptedId}/reset-2fa',
            [CompanyUserController::class, 'reset2fa']
        )->name('users.reset-2fa');

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

        // Backward-compatibility fallback:
        // some older builds still generate route('company.items.try-on').
        Route::get('/items/{encryptedId}/try-on', function ($slug, $encryptedId) {
            return redirect()
                ->route('company.items.index', $slug)
                ->with('error', 'Try-On module is not enabled for this panel.');
        })->name('items.try-on');

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

        Route::delete('/other-charge/{id}', [OtherChargeController::class, 'destroy'])
            ->name('other-charge.destroy');

        Route::get(
            '/other-charge/options',
            [OtherChargeController::class, 'options']
        )->name('other-charge.options');

        Route::get('production-cost', [ProductionCostController::class, 'index'])
            ->name('production-cost.index');

        Route::get('production-cost/create', [ProductionCostController::class, 'create'])
            ->name('production-cost.create');

        Route::post('production-cost/store', [ProductionCostController::class, 'store'])
            ->name('production-cost.store');

        Route::get('production-cost/edit/{id}', [ProductionCostController::class, 'edit'])
            ->name('production-cost.edit');

        Route::post('production-cost/update/{id}', [ProductionCostController::class, 'update'])
            ->name('production-cost.update');

        Route::delete('production-cost/{id}', [ProductionCostController::class, 'destroy'])
            ->name('production-cost.destroy');

        Route::get('labour-formula', [LabourFormulaController::class, 'index'])
            ->name('labour-formula.index');

        Route::get('labour-formula/create', [LabourFormulaController::class, 'create'])
            ->name('labour-formula.create');

        Route::post('labour-formula/store', [LabourFormulaController::class, 'store'])
            ->name('labour-formula.store');

        Route::get('labour-formula/edit/{id}', [LabourFormulaController::class, 'edit'])
            ->name('labour-formula.edit');

        Route::post('labour-formula/update/{id}', [LabourFormulaController::class, 'update'])
            ->name('labour-formula.update');

        Route::delete('labour-formula/{id}', [LabourFormulaController::class, 'destroy'])
            ->name('labour-formula.destroy');

        Route::get('production-step', [ProductionStepController::class, 'index'])
            ->name('production-step.index');

        Route::get('production-step/create', [ProductionStepController::class, 'create'])
            ->name('production-step.create');

        Route::post('production-step/store', [ProductionStepController::class, 'store'])
            ->name('production-step.store');

        Route::get('production-step/edit/{id}', [ProductionStepController::class, 'edit'])
            ->name('production-step.edit');

        Route::post('production-step/update/{id}', [ProductionStepController::class, 'update'])
            ->name('production-step.update');

        Route::delete('production-step/{id}', [ProductionStepController::class, 'destroy'])
            ->name('production-step.destroy');

        Route::get('vacuum-buchs', [VacuumBuchController::class, 'index'])
            ->name('vacuum-buchs.index');

        Route::get('vacuum-buchs/create', [VacuumBuchController::class, 'create'])
            ->name('vacuum-buchs.create');

        Route::post('vacuum-buchs/store', [VacuumBuchController::class, 'store'])
            ->name('vacuum-buchs.store');

        Route::get('vacuum-buchs/edit/{id}', [VacuumBuchController::class, 'edit'])
            ->name('vacuum-buchs.edit');

        Route::post('vacuum-buchs/update/{id}', [VacuumBuchController::class, 'update'])
            ->name('vacuum-buchs.update');

        Route::delete('vacuum-buchs/{id}', [VacuumBuchController::class, 'destroy'])
            ->name('vacuum-buchs.destroy');

        Route::get('vacuum-processes', [VacuumProcessController::class, 'index'])
            ->name('vacuum-processes.index');

        Route::get('vacuum-processes/create', [VacuumProcessController::class, 'create'])
            ->name('vacuum-processes.create');

        Route::post('vacuum-processes/store', [VacuumProcessController::class, 'store'])
            ->name('vacuum-processes.store');

        Route::get('vacuum-processes/edit/{id}', [VacuumProcessController::class, 'edit'])
            ->name('vacuum-processes.edit');

        Route::post('vacuum-processes/update/{id}', [VacuumProcessController::class, 'update'])
            ->name('vacuum-processes.update');

        Route::delete('vacuum-processes/{id}', [VacuumProcessController::class, 'destroy'])
            ->name('vacuum-processes.destroy');

        Route::get('vacuum-vouchers', [VacuumVoucherController::class, 'index'])
            ->name('vacuum-vouchers.index');

        Route::get('vacuum-vouchers/create', [VacuumVoucherController::class, 'create'])
            ->name('vacuum-vouchers.create');

        Route::post('vacuum-vouchers/store', [VacuumVoucherController::class, 'store'])
            ->name('vacuum-vouchers.store');

        Route::get('vacuum-vouchers/buch-options', [VacuumVoucherController::class, 'buchOptions'])
            ->name('vacuum-vouchers.buch-options');

        Route::get('vacuum-vouchers/{id}/view', [VacuumVoucherController::class, 'show'])
            ->name('vacuum-vouchers.show');

        Route::get('vacuum-vouchers/{id}/pdf', [VacuumVoucherController::class, 'pdf'])
            ->name('vacuum-vouchers.pdf');

        Route::get('vacuum-vouchers/edit/{id}', [VacuumVoucherController::class, 'edit'])
            ->name('vacuum-vouchers.edit');

        Route::post('vacuum-vouchers/update/{id}', [VacuumVoucherController::class, 'update'])
            ->name('vacuum-vouchers.update');

        Route::delete('vacuum-vouchers/{id}', [VacuumVoucherController::class, 'destroy'])
            ->name('vacuum-vouchers.destroy');

        Route::get('vacuum-live-dashboard', [VacuumLiveDashboardController::class, 'index'])
            ->name('vacuum-live-dashboard.index');

        Route::get('casting-heating', [CastingHeatingController::class, 'index'])
            ->name('casting-heating.index');

        Route::get('casting-heating/{id}/view', [CastingHeatingController::class, 'show'])
            ->name('casting-heating.show');

        Route::get('casting-heating/{id}/pdf', [CastingHeatingController::class, 'pdf'])
            ->name('casting-heating.pdf');

        Route::post('casting-heating/{id}/update', [CastingHeatingController::class, 'update'])
            ->name('casting-heating.update');

        Route::get('casting-metal-issue', [CastingMetalIssueController::class, 'index'])
            ->name('casting-metal-issue.index');

        Route::get('casting-metal-issue/{id}/view', [CastingMetalIssueController::class, 'show'])
            ->name('casting-metal-issue.show');

        Route::get('casting-metal-issue/{id}/pdf', [CastingMetalIssueController::class, 'pdf'])
            ->name('casting-metal-issue.pdf');

        Route::post('casting-metal-issue/{id}/update', [CastingMetalIssueController::class, 'update'])
            ->name('casting-metal-issue.update');

        Route::get('casting-release', [CastingReleaseController::class, 'index']);

        Route::get('casting-release/{id}/view', [CastingReleaseController::class, 'show']);

        Route::get('casting-release/{id}/pdf', [CastingReleaseController::class, 'pdf']);

        Route::post('casting-release/{id}/update', [CastingReleaseController::class, 'update']);

        Route::get('casting-receive', [CastingReleaseController::class, 'index'])
            ->name('casting-release.index');

        Route::get('casting-receive/{id}/view', [CastingReleaseController::class, 'show'])
            ->name('casting-release.show');

        Route::get('casting-receive/{id}/pdf', [CastingReleaseController::class, 'pdf'])
            ->name('casting-release.pdf');

        Route::post('casting-receive/{id}/update', [CastingReleaseController::class, 'update'])
            ->name('casting-release.update');

        Route::get('tree-cutting-issue', [TreeCuttingIssueController::class, 'index'])
            ->name('tree-cutting-issue.index');

        Route::get('tree-cutting-issue/{id}/view', [TreeCuttingIssueController::class, 'show'])
            ->name('tree-cutting-issue.show');

        Route::get('tree-cutting-issue/{id}/pdf', [TreeCuttingIssueController::class, 'pdf'])
            ->name('tree-cutting-issue.pdf');

        Route::post('tree-cutting-issue/{id}/update', [TreeCuttingIssueController::class, 'update'])
            ->name('tree-cutting-issue.update');

        Route::get('tree-cutting-receive', [TreeCuttingReceiveController::class, 'index'])
            ->name('tree-cutting-receive.index');

        Route::get('tree-cutting-receive/{id}/view', [TreeCuttingReceiveController::class, 'show'])
            ->name('tree-cutting-receive.show');

        Route::get('tree-cutting-receive/{id}/pdf', [TreeCuttingReceiveController::class, 'pdf'])
            ->name('tree-cutting-receive.pdf');

        Route::post('tree-cutting-receive/{id}/update', [TreeCuttingReceiveController::class, 'update'])
            ->name('tree-cutting-receive.update');

        Route::get('casting-sorting', [CastingSortingController::class, 'index'])
            ->name('casting-sorting.index');

        Route::get('casting-sorting/{id}/view', [CastingSortingController::class, 'show'])
            ->name('casting-sorting.show');

        Route::get('casting-sorting/{id}/pdf', [CastingSortingController::class, 'pdf'])
            ->name('casting-sorting.pdf');

        Route::post('casting-sorting/{id}/update', [CastingSortingController::class, 'update'])
            ->name('casting-sorting.update');

        Route::get('voucher-history/data/{voucher}', [VoucherHistoryController::class, 'data'])
            ->name('voucher-history.data');

        Route::get('voucher-history', [VoucherHistoryController::class, 'index'])
            ->name('voucher-history.index');

        Route::get('/itemsets', [ItemSetController::class, 'list_data'])
            ->name('itemsets.list_data');

        Route::get('/list_itemset', [ItemSetController::class, 'list_data'])
            ->name('list_itemset');

        Route::get('/itemsets/{encryptedId}/edit', [ItemSetController::class, 'edit'])
            ->name('itemsets.edit');

        Route::post('/itemsets/{encryptedId}/update', [ItemSetController::class, 'update'])
            ->name('itemsets.update');

        Route::delete('/itemsets/{encryptedId}', [ItemSetController::class, 'destroy'])
            ->name('itemsets.delete');

        Route::get('/item-sets', [ItemSetController::class, 'index'])
            ->name('item_sets.index');

        Route::post('/item-sets/save-cell', [ItemSetController::class, 'saveCell'])
            ->name('item_sets.saveCell');

        Route::get('/item-sets/load', [ItemSetController::class, 'loadMore'])
            ->name('item_sets.load');

        Route::post(
            '/item-sets/finalize',
            [ItemSetController::class, 'finalize']
        )->name('item_sets.finalize');
        Route::get(
            '/item-sets/finalize',
            [ItemSetController::class, 'finalizeGet']
        )->name('item_sets.finalize.get');

        Route::get('/qr-image/{code}', function ($code) {

            $qr = new QrCode(
                data: $code,
                size: 200
            );

            $writer = new PngWriter();

            return response(
                $writer->write($qr)->getString(),
                200,
                ['Content-Type' => 'image/png']
            );
        })->name('company.qr.image');
        Route::get(
            '/get-item-details/{item}',
            [ItemSetController::class, 'getItemDetails']
        )->name('get-item-details');

        Route::get(
            '/item-sets/qr-list',
            [ItemSetController::class, 'qrList']
        )->name('item_sets.qrList');

        Route::get(
            '/item-sets/qr/{id}',
            [ItemSetController::class, 'showQr']
        )->name('label-config.qr');

        Route::get(
            '/item-sets/qr-image/{id}',
            [ItemSetController::class, 'generateQrImage']
        )->name('item_sets.qrImage');

        Route::get(
            '/item-sets/print-preview/',
            [ItemSetController::class, 'printPreview']
        )->name('item_sets.printPreview');
        Route::post(
            '/item-sets/print-preview/',
            [ItemSetController::class, 'printPreview']
        )->name('item_sets.printPreview.post');

        Route::get(
            '/item-sets/print-pdf/',
            [ItemSetController::class, 'printPdf']
        )->name('item_sets.printPdf');
        Route::post(
            '/item-sets/print-pdf/',
            [ItemSetController::class, 'printPdf']
        )->name('item_sets.printPdf.post');
        Route::post(
            '/item-sets/print-direct/',
            [ItemSetController::class, 'printDirect']
        )->name('item_sets.printDirect.post');

        Route::get('sales', [SaleController::class, 'index'])
            ->name('sales.index');

        Route::get('sales/export/pdf', [SaleController::class, 'exportListPdf'])
            ->name('sales.export.pdf');

        Route::get('sales/create', [SaleController::class, 'create'])
            ->name('sales.create');

        Route::post('sales/store', [SaleController::class, 'store'])
            ->name('sales.store');
        Route::get('sales/{encryptedId}/edit', [SaleController::class, 'edit'])
            ->name('sales.edit');
        Route::post('sales/{encryptedId}/update', [SaleController::class, 'update'])
            ->name('sales.update');

        Route::get('sales/get-itemset', [SaleController::class, 'getItemset'])
            ->name('sales.getItemset');
        Route::get('sales/customer-advance', [SaleController::class, 'customerAdvance'])
            ->name('sales.customerAdvance');

        Route::get(
            '/sales/search',
            [SaleReturnController::class, 'searchSales']
        )->name('sales.search');

        Route::get('/sales/advance-ledger', [CustomerAdvanceController::class, 'index'])
            ->name('sales.advance.index');
        Route::get('/sales/advance-ledger/data', [CustomerAdvanceController::class, 'data'])
            ->name('sales.advance.data');
        Route::get('/sales/advance-ledger/pdf', [CustomerAdvanceController::class, 'exportPdf'])
            ->name('sales.advance.pdf');
        Route::post('/sales/advance-ledger', [CustomerAdvanceController::class, 'store'])
            ->name('sales.advance.store');

        Route::get('sales/{encryptedId}', [SaleController::class, 'show'])
            ->name('sales.show');

        Route::get('/sales/{encryptedId}/pdf', [SaleController::class, 'viewPdf'])
            ->name('sales.pdf');

        Route::get('/item-search', [SaleController::class, 'search'])
            ->name('items.search');

        Route::get('/approval-items', [SaleController::class, 'approvalItems'])
            ->name('sales.approval.items');

        Route::get(
            '/returns',
            [SaleReturnController::class, 'index']
        )->name('returns.index');

        Route::get(
            '/returns/export/pdf',
            [SaleReturnController::class, 'exportListPdf']
        )->name('returns.export.pdf');

        Route::get(
            '/returns/select-sale',
            [SaleReturnController::class, 'selectSale']
        )->name('returns.selectSale');

        Route::get(
            '/returns/create/{encryptedSaleId}',
            [SaleReturnController::class, 'create']
        )->name('returns.create');

        Route::post(
            '/returns/process-selected',
            [SaleReturnController::class, 'store']
        )->name('returns.processSelected');

        Route::post(
            '/returns/{encryptedSaleId}',
            [SaleReturnController::class, 'store']
        )->name('returns.store');

        Route::get(
            '/returns/{encryptedReturnId}/pdf',
            [SaleReturnController::class, 'pdf']
        )->name('returns.pdf');
        Route::get(
            '/returns/{encryptedReturnId}/view',
            [SaleReturnController::class, 'show']
        )->name('returns.show');

        Route::get(
            '/returns/select-sale-data',
            [SaleReturnController::class, 'getSalesForReturn']
        )->name('returns.selectSaleData');

        Route::post(
            '/returns/{encryptedSaleId}',
            [SaleReturnController::class, 'store']
        )->name('sales.return.store');

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

        Route::get('approval', [ApprovalController::class, 'index'])
            ->name('approval.index');

        Route::get('approval/data', [ApprovalController::class, 'index'])
            ->name('approval.data');
        Route::get('approval/export/pdf', [ApprovalController::class, 'exportListPdf'])
            ->name('approval.export.pdf');

        Route::get('approval/create', [ApprovalController::class, 'create'])
            ->name('approval.create');
        Route::get('approval/{encryptedId}/edit', [ApprovalController::class, 'edit'])
            ->name('approval.edit');

        Route::post('approval/store', [ApprovalController::class, 'store'])
            ->name('approval.store');
        Route::post('approval/{encryptedId}/update', [ApprovalController::class, 'update'])
            ->name('approval.update');

        Route::get('approval/{encryptedId}/view', [ApprovalController::class, 'view'])
            ->name('approval.view');

        Route::get('approval/{encryptedId}/pdf', [ApprovalController::class, 'pdf'])
            ->name('approval.pdf');
        Route::get('approvals/pdf/{encryptedId}', [ApprovalController::class, 'pdf'])
            ->name('approval.pdf.v2');

        Route::get('approval/{encryptedId}/items', [ApprovalController::class, 'itemsData'])
            ->name('approval.items.data');

        Route::post('approval/sale', [ApprovalController::class, 'sale'])
            ->name('approval.sale');

        Route::post('approval/return', [ApprovalController::class, 'returnItems'])
            ->name('approval.return');

        Route::get('/approval/itemsets/{item}', [ApprovalController::class, 'getItemSets'])
            ->name('approval.itemsets');

        Route::get(
            'approval/search-itemsets',
            [ApprovalController::class, 'searchItemSets']
        )->name('approval.searchItemSets');

        Route::get(
            '/approval-return-items',
            [ApprovalController::class, 'returnItems']
        )->name('approval.returnItems');

        // ================= REPORTS =================
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/barcode-history/suggest', [ReportController::class, 'barcodeHistorySuggest'])
                ->name('barcode-history.suggest');
            Route::get('/sales-summary', [ReportController::class, 'salesSummary'])
                ->name('sales-summary.index');
            Route::get('/sales-summary/export/excel', [ReportController::class, 'salesSummaryExcel'])
                ->name('sales-summary.export.excel');
            Route::get('/sales-summary/export/pdf', [ReportController::class, 'salesSummaryPdf'])
                ->name('sales-summary.export.pdf');
            Route::get('/purchase-receiver-summary', [ReportController::class, 'purchaseReceiverSummary'])
                ->name('purchase-receiver-summary.index');
            Route::get('/purchase-receiver-summary/export/excel', [ReportController::class, 'purchaseReceiverSummaryExcel'])
                ->name('purchase-receiver-summary.export.excel');
            Route::get('/purchase-receiver-summary/export/pdf', [ReportController::class, 'purchaseReceiverSummaryPdf'])
                ->name('purchase-receiver-summary.export.pdf');
            Route::get('/stock-position', [ReportController::class, 'stockPosition'])
                ->name('stock-position.index');
            Route::get('/stock-position/export/excel', [ReportController::class, 'stockPositionExcel'])
                ->name('stock-position.export.excel');
            Route::get('/stock-position/export/pdf', [ReportController::class, 'stockPositionPdf'])
                ->name('stock-position.export.pdf');
            Route::get('/approval-outstanding', [ReportController::class, 'approvalOutstanding'])
                ->name('approval-outstanding.index');
            Route::get('/approval-outstanding/{approval}/details', [ReportController::class, 'approvalOutstandingDetails'])
                ->whereNumber('approval')
                ->name('approval-outstanding.details');
            Route::get('/approval-outstanding/export/excel', [ReportController::class, 'approvalOutstandingExcel'])
                ->name('approval-outstanding.export.excel');
            Route::get('/approval-outstanding/export/pdf', [ReportController::class, 'approvalOutstandingPdf'])
                ->name('approval-outstanding.export.pdf');
            Route::get('/outstanding-amount', [ReportController::class, 'outstandingAmount'])
                ->name('outstanding-amount.index');
            Route::get('/outstanding-amount/export/excel', [ReportController::class, 'outstandingAmountExcel'])
                ->name('outstanding-amount.export.excel');
            Route::get('/outstanding-amount/export/pdf', [ReportController::class, 'outstandingAmountPdf'])
                ->name('outstanding-amount.export.pdf');
            Route::get('/outstanding-amount/export/ledger-pdf', [ReportController::class, 'outstandingAmountLedgerPdf'])
                ->name('outstanding-amount.export.ledger-pdf');
            Route::get('/barcode-history', [ReportController::class, 'barcodeHistory'])
                ->name('barcode-history.index');
            Route::get('/worker-loss/suggest', [ReportController::class, 'workerLossSuggest'])
                ->name('worker-loss.suggest');
            Route::get('/worker-loss', [ReportController::class, 'workerLoss'])
                ->name('worker-loss.index');
            Route::get('/worker-loss/export/excel', [ReportController::class, 'workerLossExcel'])
                ->name('worker-loss.export.excel');
            Route::get('/worker-loss/export/pdf', [ReportController::class, 'workerLossPdf'])
                ->name('worker-loss.export.pdf');
            Route::get('/barcode-history/export/excel', [ReportController::class, 'barcodeHistoryExcel'])
                ->name('barcode-history.export.excel');
            Route::get('/barcode-history/export/pdf', [ReportController::class, 'barcodeHistoryPdf'])
                ->name('barcode-history.export.pdf');
            Route::get('/visiting-cards', [ReportController::class, 'visitingCards'])
                ->name('visiting-cards.index');
            Route::get('/visiting-cards/create', [ReportController::class, 'visitingCardsCreate'])
                ->name('visiting-cards.create');
            Route::post('/visiting-cards/extract-bulk', [VisitingCardApiWebController::class, 'extractBulk'])
                ->name('visiting-cards.extract-bulk');
            Route::post('/visiting-cards/bulk-save', [VisitingCardApiWebController::class, 'bulkSave'])
                ->name('visiting-cards.bulk-save');
            Route::get('/visiting-cards/{id}', [ReportController::class, 'visitingCardsShow'])
                ->whereNumber('id')
                ->name('visiting-cards.show');
            Route::put('/visiting-cards/{id}', [ReportController::class, 'visitingCardsUpdate'])
                ->whereNumber('id')
                ->name('visiting-cards.update');
            Route::delete('/visiting-cards/{id}', [ReportController::class, 'visitingCardsDestroy'])
                ->whereNumber('id')
                ->name('visiting-cards.destroy');
            Route::get('/visiting-cards/export/excel', [ReportController::class, 'visitingCardsExcel'])
                ->name('visiting-cards.export.excel');
            Route::get('/visiting-cards/export/pdf', [ReportController::class, 'visitingCardsPdf'])
                ->name('visiting-cards.export.pdf');
        });
    });

Route::middleware(['auth', 'company.active', 'company.2fa', 'company.route.permission', 'company.notification.read'])
    ->prefix('company/{slug}')
    ->name('company.')
    ->group(function () {

        // ✅ SALES FROM APPROVAL
        Route::get('approval-sales/from-approval', [SaleController::class, 'approvalList'])
            ->name('approval-sales.fromApproval');

        Route::get('approval-sales/approval/{encryptedId}', [SaleController::class, 'approvalItems'])
            ->name('approval-sales.approval.items');

        Route::post('approval-sales/store-from-approval', [SaleController::class, 'storeFromApproval'])
            ->name('approval-sales.store.fromApproval');

        Route::get('approval-return', [ApprovalController::class, 'approvalReturnList'])
            ->name('approval.return.list');

        Route::get('approval-return/{encryptedId}', [ApprovalController::class, 'approvalReturnItems'])
            ->name('approval.return.items');

        Route::post('approval-return/store', [ApprovalController::class, 'approvalReturnStore'])
            ->name('approval.return.store');
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
