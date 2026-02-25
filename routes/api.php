<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::post('/company/login', [AuthController::class, 'login']);
Route::post('/company/verify-otp', [AuthController::class, 'verifyOtp']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/company/logout', [AuthController::class, 'logout']);
    Route::get('/company/me', [AuthController::class, 'me']);
});