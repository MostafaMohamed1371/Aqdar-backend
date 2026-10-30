<?php

namespace App\Modules\Dashboard\Providers;

use App\Core\Module\ModuleServiceProvider;
use App\Modules\Catalog\Services\MasterDataService;
use App\Modules\Dashboard\Services\AuditSearchService;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Dashboard\Services\ReportService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewContract;

class DashboardModuleServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Dashboard';
    }

    public function register(): void
    {
        $this->app->singleton(DashboardService::class);
        $this->app->singleton(ReportService::class);
        $this->app->singleton(AuditSearchService::class);
    }

    public function boot(): void
    {
        $this->loadDashboardWebRoutes();
        $this->loadMigrations();
        $this->loadViews();

        View::composer('dashboard::partials.reference-tabs', function (ViewContract $view): void {
            $view->with('referenceCounts', $this->app->make(MasterDataService::class)->counts());
        });

        View::composer('dashboard::partials.sidebar', function (ViewContract $view): void {
            $view->with('queueBadges', auth()->check() ? $this->app->make(DashboardService::class)->queueBadges() : []);
        });
    }

    protected function loadRoutes(): void
    {
        //
    }

    protected function loadDashboardWebRoutes(): void
    {
        $webRoutes = $this->modulePath('Routes/web.php');

        if (is_file($webRoutes)) {
            Route::middleware('web')->group($webRoutes);
        }
    }
}
