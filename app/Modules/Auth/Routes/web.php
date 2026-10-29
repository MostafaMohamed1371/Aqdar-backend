<?php

use App\Modules\Auth\Http\Controllers\AuthWebController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::redirect('login', '/dashboard')->name('login');
    Route::post('login', [AuthWebController::class, 'login'])->middleware('throttle:login');
    Route::match(['GET', 'POST'], 'register', fn () => redirect()->route('dashboard'))->name('register');
    Route::get('forgot-password', [AuthWebController::class, 'showForgotPassword'])->name('password.request');
    Route::post('forgot-password', [AuthWebController::class, 'sendResetLink'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
    Route::get('reset-password/{token}', [AuthWebController::class, 'showResetPassword'])->name('password.reset');
    Route::post('reset-password', [AuthWebController::class, 'resetPassword'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::post('logout', [AuthWebController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');
