<?php

namespace App\Modules\Finance\Providers;

use App\Core\Module\ModuleServiceProvider;
use App\Modules\Finance\Services\CommissionService;

class FinanceModuleServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Finance';
    }

    public function register(): void
    {
        $this->app->singleton(CommissionService::class);
    }
}
