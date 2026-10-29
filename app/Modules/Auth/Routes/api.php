<?php

use App\Modules\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:api-auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:otp');
    Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:password-reset');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:password-reset');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [AuthController::class, 'me']);

    Route::middleware('marketplace.active')->group(function (): void {
        Route::patch('me', [AuthController::class, 'updateMe']);
        Route::post('devices', [AuthController::class, 'registerDevice']);
    });
});
