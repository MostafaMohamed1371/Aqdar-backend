<?php

namespace Tests\Concerns;

use App\Core\Enums\StaffRole;
use App\Modules\Staff\Database\Seeders\StaffSeeder;
use App\Modules\Staff\Models\Role;
use App\Modules\Staff\Models\StaffUser;

trait CreatesStaffUsers
{
    protected function seedStaffRbac(): void
    {
        $this->seed(StaffSeeder::class);
    }

    /** @param array<string, mixed> $attributes */
    protected function makeStaff(StaffRole|string $role = StaffRole::SuperAdmin, array $attributes = []): StaffUser
    {
        $this->seedStaffRbac();

        $slug = $role instanceof StaffRole ? $role->value : $role;

        return StaffUser::factory()->create(array_merge([
            'role_id' => Role::query()->where('slug', $slug)->value('id'),
            'password' => 'password123',
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    protected function actingAsStaff(StaffRole|string $role = StaffRole::SuperAdmin, array $attributes = []): StaffUser
    {
        $staff = $this->makeStaff($role, $attributes);
        $this->actingAs($staff);

        return $staff;
    }
}
