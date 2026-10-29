<?php

namespace App\Modules\Staff\Database\Seeders;

use App\Core\Enums\Permission as PermissionEnum;
use App\Core\Enums\StaffRole;
use App\Modules\Staff\Models\Permission;
use App\Modules\Staff\Models\Role;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionEnum::cases() as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission->value],
                ['name_ar' => $permission->labelAr()],
            );
        }

        foreach (StaffRole::cases() as $staffRole) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $staffRole->value],
                [
                    'name_ar' => $staffRole->labelAr(),
                    'name_en' => $staffRole->labelEn(),
                ],
            );

            $permissionIds = Permission::query()
                ->whereIn('slug', array_map(fn (PermissionEnum $permission) => $permission->value, $staffRole->permissions()))
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }

        $superAdminRole = Role::query()->where('slug', StaffRole::SuperAdmin->value)->firstOrFail();

        StaffUser::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'AQDAR Admin',
                'password' => 'password',
                'role_id' => $superAdminRole->id,
                'is_active' => true,
            ],
        );
    }
}
