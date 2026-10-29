<?php

namespace App\Modules\Setting\Providers;

use App\Core\Cache\CachedRepository;
use App\Core\Module\ModuleServiceProvider;
use App\Modules\Setting\Repositories\SettingRepository;
use App\Modules\Setting\Services\SettingService;

class SettingModuleServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Setting';
    }

    public function register(): void
    {
        $this->app->singleton(SettingRepository::class);

        $this->app->when(SettingService::class)
            ->needs(CachedRepository::class)
            ->give(fn () => new CachedRepository('settings'));

        $this->app->singleton(SettingService::class);
    }
}
