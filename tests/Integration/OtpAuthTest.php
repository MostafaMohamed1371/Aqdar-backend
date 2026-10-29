<?php

namespace Tests\Integration;

use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\OtpService;
use App\Modules\Auth\Services\Sms\SmsGateway;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_send_does_not_return_the_code(): void
    {
        $this->postJson('/api/v1/auth/otp/send', $this->otpSendBody('01012345678'))
            ->assertOk()
            ->assertJsonMissingPath('code')
            ->assertJsonMissingPath('data.code')
            ->assertJsonPath('resend_after', 45)
            ->assertJsonPath('consent_required', false);
    }

    public function test_first_otp_send_requires_consent(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => '01012345678',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['consent', 'consent_privacy', 'consent_terms']);
    }

    public function test_otp_send_consent_covers_first_verify(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => '01012345678',
            'consent' => true,
            'locale' => 'en',
        ])->assertOk()
            ->assertJsonPath('consent_required', false);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '01012345678',
            'code' => '123456',
        ])->assertOk()
            ->assertJsonPath('data.user.locale', 'en')
            ->assertJsonPath('data.user.consent_version', '1');
    }

    public function test_otp_verify_creates_a_buyer_seller_identity(): void
    {
        $this->postJson('/api/v1/auth/otp/send', $this->otpSendBody('01012345678'));

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '01012345678',
            'code' => '123456',
            ...$this->otpConsent(['locale' => 'ar']),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.phone', '+201012345678')
            ->assertJsonPath('data.user.is_buyer', true)
            ->assertJsonPath('data.user.is_seller', false)
            ->assertJsonMissingPath('data.code');

        $this->assertDatabaseCount('users', 1);
        $this->assertNotNull(User::query()->first()?->consent_privacy_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.otp_verified']);
    }

    public function test_first_otp_verify_requires_consent_when_send_cache_is_missing(): void
    {
        $this->postJson('/api/v1/auth/otp/send', $this->otpSendBody('01012345678'));
        Cache::forget('otp:consent:+201012345678');

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '01012345678',
            'code' => '123456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['consent_privacy', 'consent_terms']);
    }

    public function test_returning_otp_user_does_not_need_consent_again(): void
    {
        $this->postJson('/api/v1/auth/otp/send', $this->otpSendBody('01012345678'));
        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '01012345678',
            'code' => '123456',
            ...$this->otpConsent(),
        ])->assertOk();

        $this->postJson('/api/v1/auth/otp/send', ['phone' => '01012345678']);
        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '01012345678',
            'code' => '123456',
        ])->assertOk();
    }

    public function test_otp_cannot_be_verified_without_sending(): void
    {
        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '01012345678',
            'code' => '123456',
        ])->assertUnprocessable();
    }

    public function test_otp_code_cannot_be_reused_after_verify(): void
    {
        $this->postJson('/api/v1/auth/otp/send', $this->otpSendBody('01012345678'));

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '01012345678',
            'code' => '123456',
            ...$this->otpConsent(),
        ])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '01012345678',
            'code' => '123456',
        ])->assertUnprocessable();
    }

    public function test_otp_send_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/otp/send', $this->otpSendBody('01012345678'))->assertOk();
        }

        $this->postJson('/api/v1/auth/otp/send', $this->otpSendBody('01012345678'))->assertStatus(429);
    }

    public function test_suspended_user_cannot_verify_otp_or_patch_profile(): void
    {
        $user = User::factory()->suspended()->create([
            'phone' => '+201011111111',
        ]);

        $this->postJson('/api/v1/auth/otp/send', ['phone' => '01011111111']);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '01011111111',
            'code' => '123456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.is_suspended', true);

        $this->patchJson('/api/v1/me', ['is_seller' => true])->assertForbidden();
        $this->postJson('/api/v1/devices', [
            'device_uuid' => 'suspended-device',
            'platform' => 'ios',
        ])->assertForbidden();
    }

    public function test_suspended_user_cannot_login_with_email(): void
    {
        User::factory()->suspended()->create([
            'email' => 'suspended@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_me_and_devices_and_config(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.locale', $user->locale)
            ->assertJsonPath('data.consent_version', '1');

        $originalPhone = $user->phone;

        $this->patchJson('/api/v1/me', [
            'is_seller' => true,
            'phone' => '01000000000',
            'email' => 'hacked@example.com',
            'locale' => 'en',
        ])->assertOk()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.is_seller', true)
            ->assertJsonPath('data.is_buyer', true)
            ->assertJsonPath('data.phone', $originalPhone)
            ->assertJsonPath('data.email', $user->email);

        $this->patchJson('/api/v1/me', ['locale' => 'fr'])->assertUnprocessable();

        $this->postJson('/api/v1/devices', [
            'device_uuid' => 'device-1',
            'platform' => 'ios',
            'push_token' => 'token-1',
        ])->assertCreated();

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'device_uuid' => 'device-1',
        ]);

        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.currency', 'EGP')
            ->assertJsonPath('data.otp.resend_after', 45)
            ->assertJsonPath('data.consent.version', '1')
            ->assertJsonStructure(['data' => [
                'sales_contact' => ['name', 'phone', 'hours'],
                'consent' => ['version', 'terms_url', 'privacy_url'],
                'otp' => ['resend_after', 'length'],
                'locales',
                'default_locale',
            ]]);
    }

    public function test_me_can_set_a_first_unverified_email_but_not_change_it(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me', ['email' => 'first@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'first@example.com');

        $this->assertNull($user->fresh()->email_verified_at);

        $this->patchJson('/api/v1/me', ['email' => 'other@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'first@example.com');
    }

    public function test_me_rejects_a_duplicate_first_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create([
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me', ['email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_sms_gateway_failure_returns_503(): void
    {
        $gateway = new class implements SmsGateway
        {
            public function send(string $phone, string $message): void
            {
                throw new \RuntimeException('provider down');
            }
        };
        $this->app->instance(SmsGateway::class, $gateway);
        $this->app->forgetInstance(OtpService::class);
        $this->app->forgetInstance(AuthService::class);

        $this->postJson('/api/v1/auth/otp/send', $this->otpSendBody('01012345678'))
            ->assertStatus(503)
            ->assertJsonPath('message', __('aqdar.auth.otp_delivery_failed'));
    }

    public function test_email_login_still_works(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.user.email', 'login@example.com');
    }
}
