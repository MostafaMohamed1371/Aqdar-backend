<?php

namespace Tests\Unit;

use App\Modules\Listing\Services\ContactLeakScanner;
use App\Modules\Notification\Services\Push\FcmConfiguration;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_reports_log_driver_when_not_fcm(): void
    {
        config([
            'aqdar.notifications.push_driver' => 'log',
            'aqdar.notifications.fcm.project_id' => '',
            'aqdar.notifications.fcm.client_email' => '',
            'aqdar.notifications.fcm.private_key' => '',
            'aqdar.notifications.fcm.credentials' => '',
            'queue.default' => 'sync',
        ]);

        $status = $this->configuration()->status();

        $this->assertSame('log', $status['driver']);
        $this->assertFalse($status['ready']);
        $this->assertFalse($status['checklist'][0]['ok']);
        $this->assertSame(__('aqdar.settings.provider_log'), $status['label']);
    }

    public function test_status_ready_when_fcm_credentials_present(): void
    {
        $this->configureFcm();
        config(['queue.default' => 'sync']);

        $user = User::factory()->create();
        UserDevice::query()->create([
            'user_id' => $user->id,
            'device_uuid' => 'ios-fcm-status',
            'platform' => 'ios',
            'push_token' => 'device-token-status',
            'last_seen_at' => now(),
        ]);

        $status = $this->configuration()->status();

        $this->assertTrue($status['ready']);
        $this->assertSame('aqdar-eg', $status['project_id']);
        $this->assertSame(1, $status['devices_with_token']);
        $this->assertSame('sync', $status['queue']);
        $this->assertTrue(collect($status['checklist'])->every(fn (array $item): bool => $item['ok']));
    }

    public function test_verify_auth_requires_fcm_driver(): void
    {
        config(['aqdar.notifications.push_driver' => 'log']);

        $probe = $this->configuration()->verifyAuth();

        $this->assertFalse($probe['ok']);
        $this->assertSame(__('aqdar.notifications.fcm_verify_driver'), $probe['message']);
    }

    public function test_verify_auth_succeeds_with_oauth_token(): void
    {
        $this->configureFcm();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.config-ok',
                'expires_in' => 3600,
            ]),
        ]);

        $probe = $this->configuration()->verifyAuth();

        $this->assertTrue($probe['ok']);
        $this->assertSame(__('aqdar.notifications.fcm_verify_ok'), $probe['message']);
    }

    private function configuration(): FcmConfiguration
    {
        return new FcmConfiguration(new ContactLeakScanner);
    }

    private function configureFcm(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $pem);

        config([
            'aqdar.notifications.push_driver' => 'fcm',
            'aqdar.notifications.fcm.project_id' => 'aqdar-eg',
            'aqdar.notifications.fcm.client_email' => 'firebase-adminsdk@aqdar-eg.iam.gserviceaccount.com',
            'aqdar.notifications.fcm.private_key' => $pem,
            'aqdar.notifications.fcm.credentials' => '',
        ]);
    }
}
