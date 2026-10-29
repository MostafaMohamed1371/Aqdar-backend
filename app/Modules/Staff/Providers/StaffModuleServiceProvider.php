<?php

namespace App\Modules\Staff\Providers;

use App\Core\Enums\Permission as PermissionEnum;
use App\Core\Module\ModuleServiceProvider;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\Staff\Services\StaffService;
use Illuminate\Support\Facades\Gate;

class StaffModuleServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Staff';
    }

    public function register(): void
    {
        $this->app->singleton(StaffService::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::before(function (mixed $user, string $ability): ?bool {
            if ($user instanceof StaffUser && $user->is_active && $user->isSuperAdmin()) {
                return true;
            }

            return null;
        });

        foreach (PermissionEnum::cases() as $permission) {
            Gate::define($permission->value, function (mixed $user) use ($permission): bool {
                return $user instanceof StaffUser && $user->hasPermission($permission);
            });
        }
    }
}
