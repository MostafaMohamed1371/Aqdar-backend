<?php

namespace Tests\Integration;

use App\Core\Audit\Models\AccessLog;
use App\Core\Audit\Models\AuditLog;
use App\Core\Enums\StaffRole;
use App\Core\Identity\ContactMask;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class StaffRbacTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_reviewer_cannot_open_users_or_staff_pages(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);

        $this->get('/dashboard/users')->assertForbidden();
        $this->get('/dashboard/staff')->assertForbidden();
        $this->get('/dashboard/settings')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['action' => 'permission_denied']);
    }

    public function test_sales_can_list_users_but_cannot_reveal_phone(): void
    {
        $this->actingAsStaff(StaffRole::Sales);
        $user = User::factory()->create(['phone' => '+201012345678']);

        $this->get('/dashboard/users')
            ->assertOk()
            ->assertSee(ContactMask::phone('+201012345678'))
            ->assertDontSee('01012345678', false);

        $this->post('/dashboard/users/'.$user->id.'/reveal')->assertForbidden();
        $this->assertSame(0, AccessLog::query()->where('action', 'user.phone_revealed')->count());
    }

    public function test_operations_can_reveal_and_suspend_with_reason(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $user = User::factory()->create(['phone' => '+201012345678']);

        $this->from('/dashboard/users')
            ->post('/dashboard/users/'.$user->id.'/reveal')
            ->assertRedirect();

        $this->assertSame(1, AccessLog::query()->where('action', 'user.phone_revealed')->count());

        $this->from('/dashboard/users')
            ->post('/dashboard/users/'.$user->id.'/suspend', [
                'reason' => 'إساءة استخدام',
            ])->assertRedirect();

        $this->assertNotNull($user->fresh()->suspended_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.suspended']);
    }

    public function test_finance_cannot_manage_staff(): void
    {
        $this->actingAsStaff(StaffRole::Finance);

        $this->get('/dashboard/staff')->assertForbidden();
        $this->assertTrue(AuditLog::query()->where('action', 'permission_denied')->exists());
    }

    public function test_super_admin_can_create_staff(): void
    {
        $this->actingAsStaff(StaffRole::SuperAdmin);

        $this->get('/dashboard/staff')
            ->assertOk()
            ->assertSee(__('aqdar.staff.matrix'));

        $roleId = \App\Modules\Staff\Models\Role::query()->where('slug', StaffRole::Reviewer->value)->value('id');

        $this->post('/dashboard/staff', [
            'name' => 'Omar Reviewer',
            'email' => 'omar@aqdar.com',
            'password' => 'password123',
            'role_id' => $roleId,
        ])->assertRedirect(route('dashboard.staff.index'));

        $this->assertDatabaseHas('staff_users', ['email' => 'omar@aqdar.com']);
    }

    public function test_super_admin_can_edit_and_delete_staff(): void
    {
        $actor = $this->actingAsStaff(StaffRole::SuperAdmin);
        $reviewerRoleId = \App\Modules\Staff\Models\Role::query()->where('slug', StaffRole::Reviewer->value)->value('id');
        $salesRoleId = \App\Modules\Staff\Models\Role::query()->where('slug', StaffRole::Sales->value)->value('id');

        $member = $this->makeStaff(StaffRole::Reviewer, [
            'email' => 'nour@aqdar.com',
            'name' => 'Nour Reviewer',
        ]);

        $this->get('/dashboard/staff/'.$member->id.'/edit')
            ->assertOk()
            ->assertSee('nour@aqdar.com');

        $this->put('/dashboard/staff/'.$member->id, [
            'name' => 'Nour Ahmed',
            'email' => 'nour@aqdar.com',
            'role_id' => $salesRoleId,
        ])->assertRedirect(route('dashboard.staff.index'));

        $member->refresh();
        $this->assertSame('Nour Ahmed', $member->name);
        $this->assertSame($salesRoleId, $member->role_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.updated']);

        $this->delete('/dashboard/staff/'.$member->id)
            ->assertRedirect(route('dashboard.staff.index'));

        $this->assertDatabaseMissing('staff_users', ['email' => 'nour@aqdar.com']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.deleted']);

        $this->from('/dashboard/staff')
            ->delete('/dashboard/staff/'.$actor->id)
            ->assertRedirect()
            ->assertSessionHasErrors('staff');

        $this->assertDatabaseHas('staff_users', ['id' => $actor->id]);
        $this->assertNotNull($reviewerRoleId);
    }

    public function test_operations_cannot_edit_staff(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $member = $this->makeStaff(StaffRole::Sales, ['email' => 'sales@aqdar.com']);

        $this->get('/dashboard/staff/'.$member->id.'/edit')->assertForbidden();
        $this->put('/dashboard/staff/'.$member->id, [
            'name' => 'Changed',
            'email' => 'sales@aqdar.com',
            'role_id' => $member->role_id,
        ])->assertForbidden();
        $this->delete('/dashboard/staff/'.$member->id)->assertForbidden();
    }
}
