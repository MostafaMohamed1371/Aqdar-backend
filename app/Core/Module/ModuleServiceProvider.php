<?php

namespace App\Core\Module;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

abstract class ModuleServiceProvider extends ServiceProvider
{
    abstract protected function moduleName(): string;

    protected function modulePath(string $path = ''): string
    {
        $base = app_path('Modules/'.$this->moduleName());

        return $path === '' ? $base : $base.'/'.$path;
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadMigrations();
        $this->loadViews();
    }

    protected function loadRoutes(): void
    {
        $webRoutes = $this->modulePath('Routes/web.php');

        if (is_file($webRoutes)) {
            Route::middleware('web')
                ->prefix(Str::kebab($this->moduleName()))
                ->name(Str::kebab($this->moduleName()).'.')
                ->group($webRoutes);
        }

        $apiRoutes = $this->modulePath('Routes/api.php');

        if (is_file($apiRoutes)) {
            Route::middleware('api')
                ->prefix('api/'.config('structer.api_prefix', 'v1'))
                ->group($apiRoutes);
        }
    }

    protected function loadMigrations(): void
    {
        $migrationsPath = $this->modulePath('Database/Migrations');

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    protected function loadViews(): void
    {
        $viewsPath = $this->modulePath('Resources/views');

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, strtolower($this->moduleName()));
        }
    }
}
