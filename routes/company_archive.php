<?php

use App\Http\Controllers\Company\CompanyArchiveController;
use Illuminate\Support\Facades\Route;

Route::prefix('profile/archive')->name('profile.archive.')->group(function () {
    foreach (['prepare', 'download', 'verify', 'remove', 'restore', 'cancel'] as $action) {
        Route::post('/'.$action, [CompanyArchiveController::class, $action])->name($action);
    }
});
