<?php

use App\Modules\Listing\Http\Controllers\EstimateController;
use App\Modules\Listing\Http\Controllers\FavoriteController;
use App\Modules\Listing\Http\Controllers\PublicListingController;
use App\Modules\Listing\Http\Controllers\RecentlyViewedController;
use App\Modules\Listing\Http\Controllers\SavedSearchController;
use App\Modules\Listing\Http\Controllers\SellerFileController;
use App\Modules\Listing\Http\Controllers\SellerListingController;
use Illuminate\Support\Facades\Route;

Route::get('my/listings/{listing}/media/{media}/preview', [SellerFileController::class, 'previewMedia'])
    ->middleware(['signed', 'throttle:api'])
    ->whereNumber('listing')
    ->whereNumber('media')
    ->name('my.listings.media.preview');

Route::middleware(['optional.sanctum', 'throttle:search'])->group(function (): void {
    Route::get('listings/counts', [PublicListingController::class, 'counts']);
    Route::get('listings/compare', [PublicListingController::class, 'compare']);
    Route::get('listings', [PublicListingController::class, 'index']);
    Route::get('map', [PublicListingController::class, 'map']);
    Route::get('listings/{listing}', [PublicListingController::class, 'show'])->whereNumber('listing');
    Route::get('listings/{listing}/media', [PublicListingController::class, 'media'])->whereNumber('listing');
});

Route::middleware(['auth:sanctum', 'marketplace.active', 'profile.complete', 'throttle:api'])->group(function (): void {
    Route::get('my/listings', [SellerListingController::class, 'index']);
    Route::post('my/listings', [SellerListingController::class, 'store']);
    Route::get('my/listings/{listing}', [SellerListingController::class, 'show'])->whereNumber('listing');
    Route::get('my/listings/{listing}/preview', [SellerListingController::class, 'preview'])->whereNumber('listing');
    Route::get('my/listings/{listing}/history', [SellerListingController::class, 'history'])->whereNumber('listing');
    Route::put('my/listings/{listing}/step/{step}', [SellerListingController::class, 'saveStep'])
        ->whereNumber('listing')
        ->where('step', 'category|specs|financials|media|documents|review');
    Route::post('my/listings/{listing}/submit', [SellerListingController::class, 'submit'])->whereNumber('listing');
    Route::put('my/listings/{listing}/resubmit', [SellerListingController::class, 'resubmit'])->whereNumber('listing');

    Route::post('my/listings/{listing}/documents', [SellerFileController::class, 'storeDocument'])->whereNumber('listing');
    Route::post('my/listings/{listing}/media', [SellerFileController::class, 'storeMedia'])->whereNumber('listing');
    Route::put('my/listings/{listing}/media/order', [SellerFileController::class, 'reorderMedia'])->whereNumber('listing');
    Route::delete('my/listings/{listing}/media/{media}', [SellerFileController::class, 'destroyMedia'])->whereNumber('listing')->whereNumber('media');
    Route::get('my/documents', [SellerFileController::class, 'indexDocuments']);

    Route::post('estimates', [EstimateController::class, 'store']);
    Route::get('listings/{listing}/estimate', [EstimateController::class, 'show'])->whereNumber('listing');

    Route::get('recently-viewed', [RecentlyViewedController::class, 'index']);
    Route::delete('recently-viewed', [RecentlyViewedController::class, 'destroy']);

    Route::get('favorites', [FavoriteController::class, 'index']);
    Route::post('favorites/{listing}', [FavoriteController::class, 'store'])->whereNumber('listing');
    Route::delete('favorites/{listing}', [FavoriteController::class, 'destroy'])->whereNumber('listing');

    Route::get('saved-searches', [SavedSearchController::class, 'index']);
    Route::post('saved-searches', [SavedSearchController::class, 'store']);
    Route::patch('saved-searches/{savedSearch}', [SavedSearchController::class, 'update'])->whereNumber('savedSearch');
    Route::delete('saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy'])->whereNumber('savedSearch');
});
