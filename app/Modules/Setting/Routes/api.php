<?php

use App\Modules\Setting\Http\Controllers\ConfigController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function (): void {
    Route::get('config', [ConfigController::class, 'show']);
    Route::get('config/sales-contact', [ConfigController::class, 'salesContact']);
});

/*
| Settings CRUD is staff Blade (ADM-13). Marketplace clients use GET /config.
| Guest → 401; signed-in marketplace user → 403.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], 'settings', function () {
        abort(403, __('aqdar.http.forbidden'));
    });
    Route::match(['GET', 'PUT', 'PATCH', 'DELETE'], 'settings/{setting}', function () {
        abort(403, __('aqdar.http.forbidden'));
    })->whereNumber('setting');
    Route::get('settings/key/{key}', function () {
        abort(403, __('aqdar.http.forbidden'));
    })->where('key', '[a-zA-Z0-9_\-\.]+');
});
