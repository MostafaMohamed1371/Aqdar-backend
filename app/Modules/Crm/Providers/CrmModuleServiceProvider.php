<?php

namespace App\Modules\Crm\Providers;

use App\Core\Module\ModuleServiceProvider;
use App\Modules\Crm\Services\ActivityFeedService;
use App\Modules\Crm\Services\AppointmentService;
use App\Modules\Crm\Services\BuyerRequestService;
use App\Modules\Crm\Services\CaseService;
use App\Modules\Crm\Services\InquiryService;
use App\Modules\Crm\Services\LeadService;
use App\Modules\Crm\Services\MaskedCall\LogMaskedCallGateway;
use App\Modules\Crm\Services\MaskedCall\MaskedCallGateway;
use App\Modules\Crm\Services\Ocr\DocumentExtractor;
use App\Modules\Crm\Services\Ocr\ManualReviewExtractor;
use App\Modules\Crm\Services\RequestMatchService;
use App\Modules\Crm\Services\SupportService;
use App\Modules\Listing\Models\Listing;

class CrmModuleServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Crm';
    }

    public function register(): void
    {
        $this->app->singleton(LeadService::class);
        $this->app->singleton(InquiryService::class);
        $this->app->singleton(AppointmentService::class);
        $this->app->singleton(RequestMatchService::class);
        $this->app->singleton(CaseService::class);
        $this->app->singleton(BuyerRequestService::class);
        $this->app->singleton(ActivityFeedService::class);
        $this->app->singleton(SupportService::class);

        // Real telephony / OCR providers replace these bindings when their accounts are configured.
        $this->app->bind(MaskedCallGateway::class, LogMaskedCallGateway::class);
        $this->app->bind(DocumentExtractor::class, ManualReviewExtractor::class);
    }

    public function boot(): void
    {
        parent::boot();

        Listing::updated(function (Listing $listing): void {
            if ($listing->wasChanged('status') && $listing->status?->isPublic()) {
                app(RequestMatchService::class)->indexListing($listing);
            }
        });
    }
}
