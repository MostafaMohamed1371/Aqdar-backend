<?php

namespace Tests\Integration;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_forms_include_csrf_token(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'secure@example.com',
            'password' => 'password123',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/dashboard/login', [
                'email' => 'secure@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/dashboard/login', [
            'email' => 'secure@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_api_login_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'api-secure@example.com',
            'password' => 'password123',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'api-secure@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'api-secure@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_security_headers_are_applied(): void
    {
        $response = $this->get('/dashboard');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_passwords_are_hashed_in_database(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Secure User',
            'email' => 'hashed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$this->otpConsent(),
        ])->assertCreated();

        $user = User::query()->where('email', 'hashed@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_user_repository_uses_eloquent_parameter_binding(): void
    {
        User::factory()->create(['email' => 'bound@example.com']);

        $user = app(\App\Modules\User\Repositories\UserRepository::class)
            ->findByEmail("bound@example.com' OR 1=1 --");

        $this->assertNull($user);
    }

    public function test_users_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();

        User::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_security_check_command_passes(): void
    {
        $this->artisan('structer:security-check')
            ->assertSuccessful();
    }
}
