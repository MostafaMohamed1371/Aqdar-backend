<?php

use Illuminate\Support\Facades\Route;

/*
| Staff administer marketplace users on Blade (ADM-04).
| Marketplace clients must not CRUD /users.
| Guest → 401; signed-in Sanctum user → 403.
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], 'users', function () {
        abort(403, __('aqdar.http.forbidden'));
    });
    Route::match(['GET', 'PUT', 'PATCH', 'DELETE'], 'users/{user}', function () {
        abort(403, __('aqdar.http.forbidden'));
    })->whereNumber('user');
});
