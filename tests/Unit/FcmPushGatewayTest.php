<?php

namespace Tests\Unit;

use App\Modules\Listing\Services\ContactLeakScanner;
use App\Modules\Notification\Services\Push\FcmPushGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmPushGatewayTest extends TestCase
{
    public function test_sends_http_v1_message_without_pii(): void
    {
        $this->configureFcm();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/v1/projects/aqdar-eg/messages:send' => Http::response([
                'name' => 'projects/aqdar-eg/messages/1',
            ]),
        ]);

        $result = $this->gateway()->send('device-token-1', [
            'title' => 'تم نشر العقار',
            'body' => 'عقارك AQ-0001 أصبح مرئياً',
            'data' => ['listing_code' => 'AQ-0001', 'event' => 'listing_published'],
        ]);

        $this->assertTrue($result->ok);
        $this->assertSame('projects/aqdar-eg/messages/1', $result->response);
        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'messages:send')) {
                return false;
            }
            $body = $request->data();
            $encoded = json_encode($body);

            return ($body['message']['token'] ?? null) === 'device-token-1'
                && ($body['message']['data']['listing_code'] ?? null) === 'AQ-0001'
                && ! str_contains((string) $encoded, '2010')
                && ! str_contains((string) $encoded, 'Seller');
        });
    }

    public function test_unregistered_token_is_marked_expired(): void
    {
        $this->configureFcm();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/*' => Http::response([
                'error' => ['status' => 'UNREGISTERED', 'message' => 'Requested entity was not found.'],
            ], 404),
        ]);

        $result = $this->gateway()->send('stale-token', [
            'title' => 'Hello',
            'body' => 'World',
            'data' => ['event' => 'listing_published'],
        ]);

        $this->assertFalse($result->ok);
        $this->assertTrue($result->tokenExpired);
    }

    public function test_contact_in_payload_is_blocked_without_calling_fcm(): void
    {
        $this->configureFcm();
        Http::fake();

        $result = $this->gateway()->send('device-token-1', [
            'title' => 'Call me',
            'body' => '01011112222',
            'data' => [],
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('payload_blocked', $result->response);
        Http::assertNothingSent();
    }

    public function test_missing_credentials_does_not_call_network(): void
    {
        config([
            'aqdar.notifications.push_driver' => 'fcm',
            'aqdar.notifications.fcm.project_id' => '',
            'aqdar.notifications.fcm.client_email' => '',
            'aqdar.notifications.fcm.private_key' => '',
            'aqdar.notifications.fcm.credentials' => '',
        ]);
        Http::fake();

        $result = $this->gateway()->send('device-token-1', [
            'title' => 'Hello',
            'body' => 'World',
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('fcm_not_configured', $result->response);
        Http::assertNothingSent();
    }

    private function gateway(): FcmPushGateway
    {
        return new FcmPushGateway(new ContactLeakScanner);
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
