<?php

namespace Tests\Integration;

use App\Core\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class StaffDashboardHardeningTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_two_factor_is_off_by_default_and_login_is_direct(): void
    {
        $this->makeStaff(StaffRole::SuperAdmin, ['email' => 'direct@example.com', 'password' => 'password123']);

        $this->post('/dashboard/login', ['email' => 'direct@example.com', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated('web');
    }

    public function test_staff_must_enter_the_emailed_code_when_two_factor_is_on(): void
    {
        config(['aqdar.staff.two_factor' => true, 'mail.default' => 'array']);
        $staff = $this->makeStaff(StaffRole::SuperAdmin, ['email' => 'otp@example.com', 'password' => 'password123']);

        $this->post('/dashboard/login', ['email' => 'otp@example.com', 'password' => 'password123'])
            ->assertRedirect(route('dashboard.two-factor'));
        $this->assertGuest('web');

        $this->get('/dashboard/two-factor')->assertOk();

        $this->post('/dashboard/two-factor', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest('web');

        $this->post('/dashboard/two-factor', ['code' => $this->emailedCode()])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($staff, 'web');
    }

    public function test_two_factor_locks_after_too_many_wrong_codes(): void
    {
        config(['aqdar.staff.two_factor' => true, 'aqdar.otp.max_attempts' => 2, 'mail.default' => 'array']);
        $this->makeStaff(StaffRole::SuperAdmin, ['email' => 'lock@example.com', 'password' => 'password123']);

        $this->post('/dashboard/login', ['email' => 'lock@example.com', 'password' => 'password123']);
        $code = $this->emailedCode();

        $this->post('/dashboard/two-factor', ['code' => '111111']);
        $this->post('/dashboard/two-factor', ['code' => '222222']);
        $this->post('/dashboard/two-factor', ['code' => $code])->assertSessionHasErrors('code');

        $this->assertGuest('web');
        $this->get('/dashboard/two-factor')->assertRedirect(route('dashboard'));
    }

    public function test_two_factor_page_needs_a_pending_login(): void
    {
        $this->get('/dashboard/two-factor')->assertRedirect(route('dashboard'));
    }

    public function test_ip_allowlist_blocks_outside_addresses_and_allows_listed_ones(): void
    {
        $this->makeStaff(StaffRole::SuperAdmin, ['email' => 'ip@example.com', 'password' => 'password123']);
        config(['aqdar.staff.ip_allowlist' => ['10.0.0.0/8']]);

        $this->post('/dashboard/login', ['email' => 'ip@example.com', 'password' => 'password123'])
            ->assertForbidden();
        $this->assertGuest('web');
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.ip_blocked']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->post('/dashboard/login', ['email' => 'ip@example.com', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));
    }

    private function emailedCode(): string
    {
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $body = (string) $messages->last()->getOriginalMessage()->getTextBody();

        $this->assertSame(1, preg_match('/\b(\d{6})\b/', $body, $matches));

        return $matches[1];
    }
}
