<?php

namespace Tests\Integration;

use App\Core\Enums\StaffRole;
use App\Modules\Staff\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_guest_sees_login_form_on_dashboard(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee(__('structer.sign_in_to_dashboard'))
            ->assertSee(__('structer.email'))
            ->assertSee(__('structer.password'))
            ->assertSee('favicon.svg', false)
            ->assertDontSee(__('structer.register'))
            ->assertDontSee(route('register'), false);
    }

    public function test_login_route_redirects_to_dashboard(): void
    {
        $this->get('/login')->assertRedirect('/dashboard');
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $staff = $this->actingAsStaff();

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee(__('aqdar.executive_dashboard'))
            ->assertSee($staff->name);
    }

    public function test_user_can_login_via_dashboard_and_access_dashboard(): void
    {
        $this->makeStaff(StaffRole::SuperAdmin, [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $this->post('/dashboard/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee(__('aqdar.executive_dashboard'));
    }

    public function test_inactive_staff_cannot_login(): void
    {
        $this->makeStaff(StaffRole::Operations, [
            'email' => 'inactive@example.com',
            'password' => 'password123',
            'is_active' => false,
        ]);

        $this->from('/dashboard')->post('/dashboard/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ])->assertRedirect('/dashboard')
            ->assertSessionHasErrors('email');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee(__('structer.sign_in_to_dashboard'));
    }

    public function test_remember_me_sets_laravel_recaller_not_a_password_cookie(): void
    {
        $this->makeStaff(StaffRole::SuperAdmin, [
            'email' => 'remember@example.com',
            'password' => 'password123',
        ]);

        $this->post('/dashboard/login', [
            'email' => 'remember@example.com',
            'password' => 'password123',
            'remember' => '1',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertCookie(Auth::guard('web')->getRecallerName())
            ->assertCookieMissing('structer_remember_login');
    }

    public function test_login_form_does_not_prefill_password_after_logout(): void
    {
        $this->makeStaff(StaffRole::SuperAdmin, [
            'email' => 'remember@example.com',
            'password' => 'password123',
        ]);

        $this->post('/dashboard/login', [
            'email' => 'remember@example.com',
            'password' => 'password123',
            'remember' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->post('/logout')->assertRedirect(route('dashboard'));

        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('value="password123"', false);
    }

    public function test_deactivated_staff_is_logged_out_on_next_request(): void
    {
        $admin = $this->makeStaff(StaffRole::SuperAdmin, [
            'email' => 'session-admin@example.com',
            'password' => 'password123',
        ]);
        $sales = $this->makeStaff(StaffRole::Sales, [
            'email' => 'session-sales@example.com',
            'password' => 'password123',
        ]);

        $this->post('/dashboard/login', [
            'email' => 'session-sales@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->post(route('dashboard.staff.deactivate', $sales), ['reason' => 'Left the company'])
            ->assertRedirect();

        $this->actingAs($sales->fresh())
            ->get('/dashboard')
            ->assertRedirect(route('dashboard'));
    }

    public function test_staff_login_writes_an_audit_row(): void
    {
        $this->makeStaff(StaffRole::SuperAdmin, [
            'email' => 'audit-login@example.com',
            'password' => 'password123',
        ]);

        $this->post('/dashboard/login', [
            'email' => 'audit-login@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login',
        ]);
    }

    public function test_failed_staff_login_is_audited(): void
    {
        $this->post('/dashboard/login', [
            'email' => 'missing-staff@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failed',
        ]);
    }

    public function test_password_change_logs_staff_out_on_next_request(): void
    {
        $admin = $this->makeStaff(StaffRole::SuperAdmin, [
            'email' => 'pw-admin@example.com',
            'password' => 'password123',
        ]);
        $sales = $this->makeStaff(StaffRole::Sales, [
            'email' => 'pw-sales@example.com',
            'password' => 'password123',
        ]);

        $this->post('/dashboard/login', [
            'email' => 'pw-sales@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->put(route('dashboard.staff.update', $sales), [
                'name' => $sales->name,
                'email' => $sales->email,
                'password' => 'newpass123',
                'role_id' => $sales->role_id,
            ])
            ->assertRedirect();

        $this->actingAs($sales->fresh())
            ->get('/dashboard')
            ->assertRedirect(route('dashboard'));
    }

    public function test_role_change_logs_staff_out_on_next_request(): void
    {
        $admin = $this->makeStaff(StaffRole::SuperAdmin, [
            'email' => 'role-admin@example.com',
            'password' => 'password123',
        ]);
        $sales = $this->makeStaff(StaffRole::Sales, [
            'email' => 'role-sales@example.com',
            'password' => 'password123',
        ]);
        $reviewerRoleId = Role::query()->where('slug', StaffRole::Reviewer->value)->value('id');

        $this->post('/dashboard/login', [
            'email' => 'role-sales@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->actingAs($admin)
            ->put(route('dashboard.staff.update', $sales), [
                'name' => $sales->name,
                'email' => $sales->email,
                'role_id' => $reviewerRoleId,
            ])
            ->assertRedirect();

        $this->actingAs($sales->fresh())
            ->get('/dashboard')
            ->assertRedirect(route('dashboard'));
    }
}
