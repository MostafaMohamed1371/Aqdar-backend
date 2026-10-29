<?php

namespace App\Providers;

use App\Core\Module\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

class StructerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('structer.php'), 'structer');

        /** @var ModuleRegistry $registry */
        $registry = $this->app->make(ModuleRegistry::class);

        foreach ($registry->providers() as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        //
    }
}
