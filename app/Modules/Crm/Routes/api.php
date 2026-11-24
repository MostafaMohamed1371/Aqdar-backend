<?php

use App\Modules\Crm\Http\Controllers\AppointmentController;
use App\Modules\Crm\Http\Controllers\BuyerRequestController;
use App\Modules\Crm\Http\Controllers\InquiryController;
use App\Modules\Crm\Http\Controllers\SellerInterestController;
use App\Modules\Crm\Http\Controllers\SupportController;
use App\Modules\Crm\Http\Controllers\TransferCaseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'marketplace.active', 'profile.complete', 'throttle:api'])->group(function (): void {
    Route::post('inquiries', [InquiryController::class, 'store']);
    Route::get('my/inquiries', [InquiryController::class, 'index']);
    Route::get('my/inquiries/{inquiry}', [InquiryController::class, 'show'])->whereNumber('inquiry');
    Route::post('my/inquiries/{inquiry}/callback', [InquiryController::class, 'callback'])->whereNumber('inquiry');
    Route::get('my/interest', [SellerInterestController::class, 'all']);
    Route::get('my/listings/{listing}/interest', [SellerInterestController::class, 'index'])->whereNumber('listing');
    Route::get('appointments/slots', [AppointmentController::class, 'slots']);
    Route::post('appointments', [AppointmentController::class, 'store']);
    Route::get('my/appointments', [AppointmentController::class, 'index']);
    Route::get('my/appointments/{appointment}', [AppointmentController::class, 'show'])->whereNumber('appointment');
    Route::post('buyer-requests', [BuyerRequestController::class, 'store']);
    Route::get('my/buyer-requests', [BuyerRequestController::class, 'index']);
    Route::get('my/buyer-requests/{buyerRequest}', [BuyerRequestController::class, 'show'])->whereNumber('buyerRequest');
    Route::post('my/buyer-requests/{buyerRequest}/callback', [BuyerRequestController::class, 'callback'])->whereNumber('buyerRequest');
    Route::get('my/cases', [TransferCaseController::class, 'index']);
    Route::get('my/cases/{transferCase}', [TransferCaseController::class, 'show'])->whereNumber('transferCase');
    Route::post('my/cases/{transferCase}/documents', [TransferCaseController::class, 'storeDocument'])->whereNumber('transferCase');
    Route::post('my/cases/{transferCase}/calls', [TransferCaseController::class, 'startCall'])->whereNumber('transferCase');
    Route::get('my/support', [SupportController::class, 'showCurrent']);
    Route::post('my/support', [SupportController::class, 'store']);
    Route::get('my/support/{supportThread}', [SupportController::class, 'show'])->whereNumber('supportThread');
});
