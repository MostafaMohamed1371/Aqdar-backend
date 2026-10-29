<?php

namespace App\Modules\Dashboard\Providers;

use App\Core\Module\ModuleServiceProvider;
use App\Modules\Dashboard\Services\AuditSearchService;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Dashboard\Services\ReportService;
use Illuminate\Support\Facades\Route;

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
