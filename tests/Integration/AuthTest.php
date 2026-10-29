<?php

namespace Tests\Integration;

use App\Core\Audit\Models\AuditLog;
use App\Modules\Auth\Notifications\ResetPasswordNotification;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$this->otpConsent(['locale' => 'ar']),
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                    'token_type',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'locale' => 'ar',
        ]);
        $this->assertNotNull(User::query()->where('email', 'jane@example.com')->value('consent_privacy_at'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.registered']);
    }

    public function test_register_requires_consent(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['consent_privacy', 'consent_terms']);
    }

    public function test_register_accepts_a_single_consent_flag(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane-single@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'consent' => true,
        ])->assertCreated()
            ->assertJsonPath('data.user.email', 'jane-single@example.com');

        $this->assertNotNull(User::query()->where('email', 'jane-single@example.com')->value('consent_privacy_at'));
    }

    public function test_failed_login_is_audited_without_storing_the_password(): void
    {
        User::factory()->create(['email' => 'audit-fail@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'audit-fail@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $row = AuditLog::query()->where('action', 'auth.login_failed')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('audit-fail@example.com', $row->meta['email'] ?? null);
        $this->assertArrayNotHasKey('password', $row->meta ?? []);
    }

    public function test_successful_marketplace_login_is_audited_without_password(): void
    {
        User::factory()->create([
            'email' => 'audit-ok@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'audit-ok@example.com',
            'password' => 'password123',
        ])->assertOk();

        $row = AuditLog::query()->where('action', 'auth.login')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('sanctum', $row->meta['guard'] ?? null);
        $this->assertArrayNotHasKey('password', $row->meta ?? []);
    }

    public function test_issued_tokens_expire(): void
    {
        User::factory()->create([
            'email' => 'expire@example.com',
            'password' => 'password123',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'expire@example.com',
            'password' => 'password123',
        ])->assertOk()->json('data.token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $id = (int) explode('|', (string) $token, 2)[0];
        PersonalAccessToken::query()->whereKey($id)->update([
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subDays(40),
        ]);

        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'login@example.com')
            ->assertJsonStructure(['data' => ['token', 'token_type']]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $user->refresh();

        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }
}
