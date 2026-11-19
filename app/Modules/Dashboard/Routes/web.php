<?php

use App\Modules\Auth\Http\Controllers\AuthWebController;
use App\Modules\Dashboard\Http\Controllers\AuditDeskController;
use App\Modules\Dashboard\Http\Controllers\BuyerRequestDeskController;
use App\Modules\Dashboard\Http\Controllers\CategoryController;
use App\Modules\Dashboard\Http\Controllers\CommissionDeskController;
use App\Modules\Dashboard\Http\Controllers\ContentController;
use App\Modules\Dashboard\Http\Controllers\DashboardController;
use App\Modules\Dashboard\Http\Controllers\DeveloperController;
use App\Modules\Dashboard\Http\Controllers\DocumentDeskController;
use App\Modules\Dashboard\Http\Controllers\LeadDeskController;
use App\Modules\Dashboard\Http\Controllers\ListingDeskController;
use App\Modules\Dashboard\Http\Controllers\NotificationDeskController;
use App\Modules\Dashboard\Http\Controllers\ProfileController;
use App\Modules\Dashboard\Http\Controllers\ReferenceController;
use App\Modules\Dashboard\Http\Controllers\ReportDeskController;
use App\Modules\Dashboard\Http\Controllers\SettingController;
use App\Modules\Dashboard\Http\Controllers\StaffController;
use App\Modules\Dashboard\Http\Controllers\StaffInboxController;
use App\Modules\Dashboard\Http\Controllers\SupportDeskController;
use App\Modules\Dashboard\Http\Controllers\TransferCaseDeskController;
use App\Modules\Dashboard\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/dashboard/login', [AuthWebController::class, 'login'])
    ->middleware('throttle:login')
    ->name('dashboard.login');

Route::middleware('auth')->prefix('dashboard')->name('dashboard.')->group(function (): void {
    Route::get('inbox', [StaffInboxController::class, 'index'])->name('inbox');
    Route::post('inbox/{notification}/read', [StaffInboxController::class, 'read'])->whereNumber('notification')->name('inbox.read');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('staff.can:approve_listing')->group(function (): void {
        Route::get('listings', [ListingDeskController::class, 'index'])->name('listings.index');
        Route::post('listings/bulk', [ListingDeskController::class, 'bulk'])->name('listings.bulk');
        Route::get('listings/{listing}', [ListingDeskController::class, 'show'])->name('listings.show');
        Route::post('listings/{listing}/approve', [ListingDeskController::class, 'approve'])->name('listings.approve');
        Route::post('listings/{listing}/request-changes', [ListingDeskController::class, 'requestChanges'])->name('listings.request-changes');
        Route::post('listings/{listing}/reject', [ListingDeskController::class, 'reject'])->name('listings.reject');
        Route::post('listings/{listing}/reopen', [ListingDeskController::class, 'reopen'])->name('listings.reopen');
        Route::post('listings/{listing}/verify-financials', [ListingDeskController::class, 'verifyFinancials'])->name('listings.verify-financials');
        Route::post('listings/{listing}/media/{media}/approve', [ListingDeskController::class, 'approveMedia'])->name('listings.media.approve');
        Route::post('listings/{listing}/media/{media}/reject', [ListingDeskController::class, 'rejectMedia'])->name('listings.media.reject');
        Route::post('listings/{listing}/assign-reviewer', [ListingDeskController::class, 'assignReviewer'])->name('listings.assign-reviewer');
    });

    Route::middleware('staff.can:publish_listing')->group(function (): void {
        Route::post('listings/{listing}/publish', [ListingDeskController::class, 'publish'])->name('listings.publish');
        Route::post('listings/{listing}/unpublish', [ListingDeskController::class, 'unpublish'])->name('listings.unpublish');
        Route::post('listings/{listing}/under-offer', [ListingDeskController::class, 'underOffer'])->name('listings.under-offer');
        Route::post('listings/{listing}/hold', [ListingDeskController::class, 'hold'])->name('listings.hold');
        Route::post('listings/{listing}/close', [ListingDeskController::class, 'close'])->name('listings.close');
        Route::post('listings/{listing}/archive', [ListingDeskController::class, 'archive'])->name('listings.archive');
    });

    Route::middleware('staff.can:open_private_documents')->group(function (): void {
        Route::get('documents', [DocumentDeskController::class, 'index'])->name('documents.index');
        Route::get('documents/listing/{listing}', [DocumentDeskController::class, 'show'])->name('documents.show');
        Route::post('documents/{document}/approve', [DocumentDeskController::class, 'approve'])->name('documents.approve');
        Route::post('documents/{document}/reject', [DocumentDeskController::class, 'reject'])->name('documents.reject');
        Route::post('documents/{document}/request-replacement', [DocumentDeskController::class, 'requestReplacement'])->name('documents.request-replacement');
        Route::get('documents/{document}/download', [DocumentDeskController::class, 'download'])
            ->middleware('signed')
            ->name('documents.download');
    });

    Route::middleware('staff.can:export_csv')->group(function (): void {
        Route::get('reports', [ReportDeskController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportDeskController::class, 'export'])->name('reports.export');
    });

    Route::middleware('staff.can:manage_catalog')->group(function (): void {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::resource('categories', CategoryController::class)->except(['show']);

        Route::get('developers', [DeveloperController::class, 'index'])->name('developers.index');
        Route::post('developers', [DeveloperController::class, 'storeDeveloper'])->name('developers.store');
        Route::put('developers/{developer}', [DeveloperController::class, 'updateDeveloper'])->name('developers.update');
        Route::delete('developers/{developer}', [DeveloperController::class, 'destroyDeveloper'])->name('developers.destroy');
        Route::post('projects', [DeveloperController::class, 'storeProject'])->name('projects.store');
        Route::put('projects/{project}', [DeveloperController::class, 'updateProject'])->name('projects.update');
        Route::delete('projects/{project}', [DeveloperController::class, 'destroyProject'])->name('projects.destroy');

        Route::get('locations', [ReferenceController::class, 'locations'])->name('locations.index');
        Route::post('governorates', [ReferenceController::class, 'storeGovernorate'])->name('governorates.store');
        Route::put('governorates/{governorate}', [ReferenceController::class, 'updateGovernorate'])->name('governorates.update');
        Route::delete('governorates/{governorate}', [ReferenceController::class, 'destroyGovernorate'])->name('governorates.destroy');
        Route::post('areas', [ReferenceController::class, 'storeArea'])->name('areas.store');
        Route::put('areas/{area}', [ReferenceController::class, 'updateArea'])->name('areas.update');
        Route::delete('areas/{area}', [ReferenceController::class, 'destroyArea'])->name('areas.destroy');

        Route::get('unit-types', [ReferenceController::class, 'unitTypes'])->name('unit-types.index');
        Route::post('unit-types', [ReferenceController::class, 'storeUnitType'])->name('unit-types.store');
        Route::put('unit-types/{unitType}', [ReferenceController::class, 'updateUnitType'])->name('unit-types.update');
        Route::delete('unit-types/{unitType}', [ReferenceController::class, 'destroyUnitType'])->name('unit-types.destroy');

        Route::get('amenities', [ReferenceController::class, 'amenities'])->name('amenities.index');
        Route::post('amenities', [ReferenceController::class, 'storeAmenity'])->name('amenities.store');
        Route::put('amenities/{amenity}', [ReferenceController::class, 'updateAmenity'])->name('amenities.update');
        Route::delete('amenities/{amenity}', [ReferenceController::class, 'destroyAmenity'])->name('amenities.destroy');

        Route::get('tags', [ReferenceController::class, 'tags'])->name('tags.index');
        Route::post('tags', [ReferenceController::class, 'storeTag'])->name('tags.store');
        Route::put('tags/{tag}', [ReferenceController::class, 'updateTag'])->name('tags.update');
        Route::delete('tags/{tag}', [ReferenceController::class, 'destroyTag'])->name('tags.destroy');

        Route::post('content', [ContentController::class, 'store'])->name('content.store');
        Route::put('content/{contentBlock}', [ContentController::class, 'update'])->name('content.update');
        Route::delete('content/{contentBlock}', [ContentController::class, 'destroy'])->name('content.destroy');
        Route::put('notifications/templates/{template}', [NotificationDeskController::class, 'updateTemplate'])->whereNumber('template')->name('notifications.templates.update');
        Route::post('notifications/test-send', [NotificationDeskController::class, 'testSend'])->name('notifications.test-send');
        Route::post('notifications/fcm-verify', [NotificationDeskController::class, 'verifyFcm'])->name('notifications.fcm-verify');
        Route::post('notifications/fcm-diagnostic', [NotificationDeskController::class, 'diagnosticPush'])->name('notifications.fcm-diagnostic');
    });

    Route::middleware('staff.can:manage_catalog,approve_broadcasts')->group(function (): void {
        Route::get('content', [ContentController::class, 'index'])->name('content.index');
        Route::post('notifications/broadcasts', [NotificationDeskController::class, 'broadcast'])->name('notifications.broadcasts');
        Route::post('notifications/broadcasts/{broadcast}/approve', [NotificationDeskController::class, 'approveBroadcast'])
            ->whereNumber('broadcast')
            ->name('notifications.broadcasts.approve');
    });

    Route::middleware('staff.can:manage_crm')->group(function (): void {
        Route::get('leads', [LeadDeskController::class, 'index'])->name('leads.index');
        Route::post('leads', [LeadDeskController::class, 'store'])->name('leads.store');
        Route::get('leads/{lead}', [LeadDeskController::class, 'show'])->name('leads.show');
        Route::post('leads/{lead}/assign', [LeadDeskController::class, 'assign'])->name('leads.assign');
        Route::post('leads/{lead}/activity', [LeadDeskController::class, 'activity'])->name('leads.activity');
        Route::post('leads/{lead}/status', [LeadDeskController::class, 'status'])->name('leads.status');
        Route::post('leads/{lead}/reveal', [LeadDeskController::class, 'reveal'])->name('leads.reveal');
        Route::post('leads/{lead}/appointments/{appointment}', [LeadDeskController::class, 'appointment'])->name('leads.appointments.status');

        Route::get('requests', [BuyerRequestDeskController::class, 'index'])->name('requests.index');
        Route::post('requests', [BuyerRequestDeskController::class, 'store'])->name('requests.store');
        Route::get('requests/{buyerRequest}', [BuyerRequestDeskController::class, 'show'])->whereNumber('buyerRequest')->name('requests.show');
        Route::post('requests/{buyerRequest}/assign', [BuyerRequestDeskController::class, 'assign'])->name('requests.assign');
        Route::post('requests/{buyerRequest}/status', [BuyerRequestDeskController::class, 'status'])->name('requests.status');
        Route::post('requests/{buyerRequest}/refresh', [BuyerRequestDeskController::class, 'refresh'])->name('requests.refresh');
        Route::post('requests/{buyerRequest}/matches/attach', [BuyerRequestDeskController::class, 'attachMatch'])->name('requests.matches.attach');
        Route::post('requests/{buyerRequest}/matches/{match}/send', [BuyerRequestDeskController::class, 'sendMatch'])->name('requests.matches.send');
        Route::post('requests/{buyerRequest}/matches/{match}/response', [BuyerRequestDeskController::class, 'matchResponse'])->name('requests.matches.response');
        Route::post('requests/{buyerRequest}/matches/{match}/open-lead', [BuyerRequestDeskController::class, 'openLead'])->name('requests.matches.open-lead');
        Route::post('requests/{buyerRequest}/convert', [BuyerRequestDeskController::class, 'convert'])->name('requests.convert');

        Route::post('cases', [TransferCaseDeskController::class, 'store'])->name('cases.store');
        Route::get('cases/documents/{caseDocument}/download', [TransferCaseDeskController::class, 'download'])
            ->middleware('signed')
            ->name('cases.documents.download');
        Route::post('cases/{transferCase}/assign', [TransferCaseDeskController::class, 'assign'])->whereNumber('transferCase')->name('cases.assign');
        Route::post('cases/{transferCase}/lifecycle', [TransferCaseDeskController::class, 'lifecycle'])->whereNumber('transferCase')->name('cases.lifecycle');
        Route::post('cases/{transferCase}/documents', [TransferCaseDeskController::class, 'storeDocument'])->whereNumber('transferCase')->name('cases.documents.store');

        Route::get('support', [SupportDeskController::class, 'index'])->name('support.index');
        Route::get('support/{supportThread}', [SupportDeskController::class, 'show'])->whereNumber('supportThread')->name('support.show');
        Route::post('support/{supportThread}/reply', [SupportDeskController::class, 'reply'])->whereNumber('supportThread')->name('support.reply');
        Route::post('support/{supportThread}/close', [SupportDeskController::class, 'close'])->whereNumber('supportThread')->name('support.close');
    });

    Route::middleware('staff.can:manage_crm,advance_finance_stages')->group(function (): void {
        Route::get('cases', [TransferCaseDeskController::class, 'index'])->name('cases.index');
        Route::get('cases/{transferCase}', [TransferCaseDeskController::class, 'show'])->whereNumber('transferCase')->name('cases.show');
        Route::post('cases/{transferCase}/stage', [TransferCaseDeskController::class, 'stage'])->whereNumber('transferCase')->name('cases.stage');
    });

    Route::middleware('staff.can:view_users')->group(function (): void {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users/{user}/reveal', [UserController::class, 'reveal'])
            ->middleware('staff.can:reveal_phone')
            ->name('users.reveal');

        Route::middleware('staff.can:suspend_user')->group(function (): void {
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
            Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
            Route::post('users/{user}/close/complete', [UserController::class, 'completeClose'])->name('users.close.complete');
            Route::post('users/{user}/close/decline', [UserController::class, 'declineClose'])->name('users.close.decline');
        });
    });

    Route::middleware('staff.can:edit_commissions,approve_commissions,view_commissions')->group(function (): void {
        Route::get('commissions', [CommissionDeskController::class, 'index'])->name('commissions.index');
        Route::get('commissions/export', [CommissionDeskController::class, 'export'])->name('commissions.export');
    });
    Route::middleware('staff.can:edit_commissions')->group(function (): void {
        Route::post('commissions', [CommissionDeskController::class, 'store'])->name('commissions.store');
        Route::match(['put', 'patch'], 'commissions/{commission}', [CommissionDeskController::class, 'update'])->whereNumber('commission')->name('commissions.update');
    });
    Route::middleware('staff.can:approve_commissions')->group(function (): void {
        Route::post('commissions/{commission}/approve', [CommissionDeskController::class, 'approve'])->whereNumber('commission')->name('commissions.approve');
    });

    Route::middleware('staff.can:manage_staff')->group(function (): void {
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('staff/{staffUser}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('staff/{staffUser}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('staff/{staffUser}', [StaffController::class, 'destroy'])->name('staff.destroy');
        Route::post('staff/{staffUser}/deactivate', [StaffController::class, 'deactivate'])->name('staff.deactivate');
        Route::post('staff/{staffUser}/activate', [StaffController::class, 'activate'])->name('staff.activate');
        Route::post('staff/{staffUser}/sign-out', [StaffController::class, 'signOutEverywhere'])->name('staff.sign-out');

        Route::get('audit', [AuditDeskController::class, 'index'])->name('audit.index');
        Route::get('audit/export', [AuditDeskController::class, 'export'])->name('audit.export');
    });
});
