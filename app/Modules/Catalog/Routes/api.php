<?php

use App\Modules\Catalog\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function (): void {
    Route::get('locations', [CatalogController::class, 'locations']);
    Route::get('developers', [CatalogController::class, 'developers']);
    Route::get('projects', [CatalogController::class, 'projects']);
    Route::get('unit-types', [CatalogController::class, 'unitTypes']);
    Route::get('amenities', [CatalogController::class, 'amenities']);
    Route::get('tags', [CatalogController::class, 'tags']);
    Route::get('content/banners', [CatalogController::class, 'banners']);
    Route::get('content/faqs', [CatalogController::class, 'faqs']);
    Route::get('content/support', [CatalogController::class, 'support']);
});
