<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;

Route::post('/company/login', [AuthController::class, 'login']);
Route::post('/company/verify-otp', [AuthController::class, 'verifyOtp']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/company/logout', [AuthController::class, 'logout']);
    Route::get('/company/me', [AuthController::class, 'me']);
});



Route::middleware('auth:sanctum')->group(function () {

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

});