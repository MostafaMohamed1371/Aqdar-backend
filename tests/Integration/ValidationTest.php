<?php

namespace Tests\Integration;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class ValidationTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_api_register_returns_field_errors_for_invalid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '1',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', __('aqdar.http.validation'))
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_api_register_rejects_weak_password_without_numbers(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'passwordonly',
            'password_confirmation' => 'passwordonly',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_api_login_returns_field_errors_for_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_dashboard_login_shows_field_errors_on_web(): void
    {
        $response = $this->from('/dashboard')->post('/dashboard/login', [
            'email' => 'bad-email',
            'password' => '',
        ]);

        $response->assertRedirect('/dashboard')
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_dashboard_user_create_shows_field_errors_on_web(): void
    {
        $this->actingAsStaff();

        $response = $this->from(route('dashboard.users.create'))->post(route('dashboard.users.store'), [
            'name' => '1',
            'email' => 'not-valid',
        ]);

        $response->assertRedirect(route('dashboard.users.create'))
            ->assertSessionHasErrors(['name', 'email']);
    }

    public function test_marketplace_cannot_create_users_via_api(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/users', [
            'name' => '',
            'email' => 'invalid',
            'password' => '123',
        ]);

        $response->assertForbidden();
    }

    public function test_marketplace_cannot_update_users_via_api(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'email' => 'not-an-email',
        ]);

        $response->assertForbidden();
    }
}
