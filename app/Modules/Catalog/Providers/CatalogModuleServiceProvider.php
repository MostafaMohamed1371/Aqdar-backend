<?php

namespace App\Modules\Catalog\Providers;

use App\Core\Module\ModuleServiceProvider;
use App\Modules\Catalog\Services\CatalogReadService;
use App\Modules\Catalog\Services\MasterDataService;

class CatalogModuleServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Catalog';
    }

    public function register(): void
    {
        $this->app->singleton(CatalogReadService::class);
        $this->app->singleton(MasterDataService::class);
    }
}
