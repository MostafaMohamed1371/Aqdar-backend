<?php

use App\Modules\Notification\Http\Controllers\NotificationController;
use App\Modules\Notification\Http\Controllers\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'marketplace.active', 'throttle:api'])->group(function (): void {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereNumber('notification');
    Route::get('me/notification-preferences', [NotificationPreferenceController::class, 'show']);
    Route::patch('me/notification-preferences', [NotificationPreferenceController::class, 'update']);
    // Mobile clients call the shorter path used on the profile screen.
    Route::get('me/preferences', [NotificationPreferenceController::class, 'show']);
    Route::patch('me/preferences', [NotificationPreferenceController::class, 'update']);
});
