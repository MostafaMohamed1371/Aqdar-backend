<?php

namespace Tests\Integration;

use App\Core\Audit\Models\AuditLog;
use App\Core\Enums\AccountType;
use App\Core\Enums\StaffRole;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardUsersTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_login_page_does_not_offer_register(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee(__('structer.register'))
            ->assertDontSee(__('structer.dont_have_account'));
    }

    public function test_web_register_redirects_and_does_not_create_a_user(): void
    {
        $this->get('/register')->assertRedirect(route('dashboard'));

        $this->post('/register', [
            'name' => 'Public User',
            'email' => 'public@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('users', ['email' => 'public@example.com']);
    }

    public function test_sales_cannot_create_edit_or_delete_users(): void
    {
        $this->actingAsStaff(StaffRole::Sales);
        $user = User::factory()->create();

        $this->get('/dashboard/users')
            ->assertOk()
            ->assertDontSee(__('aqdar.users.add'), false);

        $this->get('/dashboard/users/create')->assertForbidden();
        $this->post('/dashboard/users', [
            'name' => 'New Person',
            'email' => 'new-person@example.com',
            'account_type' => AccountType::Individual->value,
        ])->assertForbidden();

        $this->get('/dashboard/users/'.$user->id.'/edit')->assertForbidden();
        $this->put('/dashboard/users/'.$user->id, [
            'name' => 'Changed Name',
            'email' => $user->email,
            'account_type' => AccountType::Individual->value,
        ])->assertForbidden();

        $this->delete('/dashboard/users/'.$user->id)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_operations_can_create_edit_and_delete_users(): void
    {
        $this->actingAsStaff(StaffRole::Operations);

        $this->get('/dashboard/users/create')->assertOk();

        $this->post('/dashboard/users', [
            'name' => 'Mona Ali',
            'email' => 'mona@example.com',
            'phone' => '01012345678',
            'account_type' => AccountType::Individual->value,
            'is_buyer' => '1',
            'is_seller' => '1',
        ])->assertRedirect(route('dashboard.users.index'));

        $user = User::query()->where('email', 'mona@example.com')->firstOrFail();
        $this->assertSame('+201012345678', $user->phone);
        $this->assertTrue($user->is_seller);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);

        $this->get('/dashboard/users/'.$user->id.'/edit')
            ->assertOk()
            ->assertSee('mona@example.com');

        $this->put('/dashboard/users/'.$user->id, [
            'name' => 'Mona Hassan',
            'email' => 'mona@example.com',
            'phone' => '01012345678',
            'account_type' => AccountType::Broker->value,
            'is_buyer' => '1',
            'is_seller' => '0',
        ])->assertRedirect(route('dashboard.users.index'));

        $user->refresh();
        $this->assertSame('Mona Hassan', $user->name);
        $this->assertSame(AccountType::Broker, $user->account_type);
        $this->assertFalse($user->is_seller);
        $this->assertTrue(AuditLog::query()->where('action', 'user.updated')->exists());

        $this->delete('/dashboard/users/'.$user->id)
            ->assertRedirect(route('dashboard.users.index'));

        $this->assertDatabaseMissing('users', ['email' => 'mona@example.com']);
        $this->assertTrue(AuditLog::query()->where('action', 'user.deleted')->exists());
    }
}
