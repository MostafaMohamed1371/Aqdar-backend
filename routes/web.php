<?php

use App\Core\Http\Controllers\LocaleController;
use App\Modules\Listing\Http\Controllers\PublicShareController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');
Route::get('/property/{code}', [PublicShareController::class, 'show'])
    ->middleware('marketplace.open')
    ->where('code', '[A-Za-z0-9\-]+')
    ->name('listings.share');
