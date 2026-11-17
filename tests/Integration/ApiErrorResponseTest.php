<?php

namespace Tests\Integration;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_json_returns_400_with_message(): void
    {
        $this->call(
            'POST',
            '/api/v1/auth/login',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '{not-json',
        )->assertStatus(400)
            ->assertJsonPath('code', 'invalid_json')
            ->assertJsonPath('message', __('aqdar.http.invalid_json'))
            ->assertJsonPath('title', __('aqdar.flash.error_title'))
            ->assertJsonPath('hint', __('aqdar.http.hint_bad_request'))
            ->assertHeader('Content-Language');
    }

    public function test_guest_me_returns_401_with_message(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated')
            ->assertJsonPath('message', __('aqdar.http.unauthenticated'))
            ->assertJsonPath('title', __('aqdar.flash.error_title'))
            ->assertJsonPath('hint', __('aqdar.http.hint_unauthenticated'))
            ->assertJsonMissingPath('errors')
            ->assertJsonMissingPath('details')
            ->assertJsonMissingPath('exception');
    }

    public function test_arabic_401_message_follows_accept_language(): void
    {
        $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', __('aqdar.http.unauthenticated', locale: 'ar'))
            ->assertHeader('Content-Language', 'ar');
    }

    public function test_locked_route_returns_403_with_message(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertJsonPath('message', __('aqdar.http.forbidden'));
    }

    public function test_unknown_api_route_returns_404_with_message(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('message', __('aqdar.http.not_found'));
    }

    public function test_wrong_method_returns_405_with_message(): void
    {
        $this->postJson('/api/v1/config')
            ->assertStatus(405)
            ->assertJsonPath('message', __('aqdar.http.method_not_allowed'));
    }

    public function test_otp_validation_returns_422_field_errors(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation')
            ->assertJsonPath('title', __('aqdar.flash.validation_title'))
            ->assertJsonPath('hint', __('aqdar.flash.validation_hint'))
            ->assertJsonValidationErrors('phone')
            ->assertJsonStructure(['details']);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '01012345678',
            'code' => '12',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code')
            ->assertJsonPath('details.0', fn ($value) => is_string($value) && $value !== '');
    }

    public function test_device_platform_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/devices', [
            'device_uuid' => 'device-1',
            'platform' => 'windows',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('platform');
    }

    public function test_server_error_returns_500_without_internals(): void
    {
        Route::middleware('api')->get('/api/v1/__test-server-error', function (): never {
            throw new \RuntimeException('secret internals');
        });

        $this->getJson('/api/v1/__test-server-error')
            ->assertStatus(500)
            ->assertJsonPath('message', __('aqdar.http.server_error'))
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('file')
            ->assertDontSee('secret internals');
    }
}
