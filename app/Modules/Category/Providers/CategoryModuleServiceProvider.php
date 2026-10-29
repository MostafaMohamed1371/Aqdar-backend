<?php

namespace App\Modules\Category\Providers;

use App\Core\Cache\CachedRepository;
use App\Core\Module\ModuleServiceProvider;
use App\Modules\Category\Repositories\CategoryRepository;
use App\Modules\Category\Services\CategoryService;

class CategoryModuleServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Category';
    }

    public function register(): void
    {
        $this->app->singleton(CategoryRepository::class);

        $this->app->when(CategoryService::class)
            ->needs(CachedRepository::class)
            ->give(fn () => new CachedRepository('categories'));

        $this->app->singleton(CategoryService::class);
    }
}
