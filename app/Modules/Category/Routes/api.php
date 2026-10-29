<?php

use App\Modules\Category\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/slug/{slug}', [CategoryController::class, 'showBySlug'])
        ->where('slug', '[a-z0-9\-]+');
});

/*
| Category writes are staff Blade (ADM-15), not marketplace REST. Public GET stays for catalog.
| Guest POST → 401; signed-in marketplace user → 403.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('categories', function () {
        abort(403, __('aqdar.http.forbidden'));
    });
    Route::match(['PUT', 'PATCH', 'DELETE'], 'categories/{category}', function () {
        abort(403, __('aqdar.http.forbidden'));
    })->whereNumber('category');
});
