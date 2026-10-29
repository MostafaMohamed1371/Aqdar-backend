<?php

namespace App\Modules\User\Providers;

use App\Core\Module\ModuleServiceProvider;
use App\Modules\User\Repositories\UserRepository;
use App\Modules\User\Services\UserService;

class UserModuleServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'User';
    }

    public function register(): void
    {
        $this->app->singleton(UserRepository::class);
        $this->app->singleton(UserService::class);
    }
}
