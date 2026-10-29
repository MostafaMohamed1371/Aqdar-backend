<?php

namespace App\Modules\Listing\Providers;

use App\Core\Module\ModuleServiceProvider;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Policies\ListingPolicy;
use App\Modules\Listing\Services\ContactLeakScanner;
use App\Modules\Listing\Services\DocumentWatermarker;
use App\Modules\Listing\Services\EstimateService;
use App\Modules\Listing\Services\ImageProcessor;
use App\Modules\Listing\Services\ListingFileService;
use App\Modules\Listing\Services\ListingModerationService;
use App\Modules\Listing\Services\ListingSearchService;
use App\Modules\Listing\Services\ListingService;
use App\Modules\Listing\Services\ListingStateMachine;
use App\Modules\Listing\Services\PdfWatermarker;
use Illuminate\Support\Facades\Gate;

class ListingModuleServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Listing';
    }

    public function register(): void
    {
        $this->app->singleton(ListingStateMachine::class);
        $this->app->singleton(ContactLeakScanner::class);
        $this->app->singleton(ImageProcessor::class);
        $this->app->singleton(PdfWatermarker::class);
        $this->app->singleton(DocumentWatermarker::class);
        $this->app->singleton(ListingFileService::class);
        $this->app->singleton(ListingModerationService::class);
        $this->app->singleton(ListingService::class);
        $this->app->singleton(ListingSearchService::class);
        $this->app->singleton(EstimateService::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Listing::class, ListingPolicy::class);
    }
}
