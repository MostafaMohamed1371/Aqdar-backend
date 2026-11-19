<?php

namespace App\Modules\Notification\Services\Push;

use App\Modules\Listing\Services\ContactLeakScanner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmPushGateway implements PushGateway
{
    public function __construct(private ContactLeakScanner $scanner) {}

    public function send(string $token, array $payload): PushResult
    {
        if ($token === '') {
            return new PushResult(false, 'missing_token', true);
        }

        $creds = $this->credentials();
        if ($creds === null) {
            return new PushResult(false, 'fcm_not_configured');
        }

        $title = (string) ($payload['title'] ?? '');
        $body = (string) ($payload['body'] ?? '');
        $data = $this->stringData((array) ($payload['data'] ?? []));
        if ($this->scanner->scan($title, $body, implode("\n", $data)) !== null) {
            return new PushResult(false, 'payload_blocked');
        }

        $access = $this->accessToken($creds);
        if ($access === '') {
            return new PushResult(false, 'fcm_auth_failed');
        }

        $response = Http::timeout(12)
            ->acceptJson()
            ->withToken($access)
            ->post('https://fcm.googleapis.com/v1/projects/'.$creds['project_id'].'/messages:send', [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                    'android' => ['priority' => 'HIGH'],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]);

        if ($response->successful()) {
            $name = (string) $response->json('name', 'fcm_ok');

            return new PushResult(true, $name);
        }

        $status = (string) ($response->json('error.status') ?? $response->status());
        $expired = in_array($status, ['UNREGISTERED', 'NOT_FOUND'], true)
            || in_array($response->status(), [404, 410], true);

        return new PushResult(false, $this->safeError($status), $expired);
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function stringData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $out[(string) $key] = is_scalar($value) ? (string) $value : (string) json_encode($value);
        }

        return $out;
    }

    public function verifyCredentials(): bool
    {
        $creds = $this->credentials();
        if ($creds === null) {
            return false;
        }

        return $this->accessToken($creds) !== '';
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string}|null
     */
    public function resolvedCredentials(): ?array
    {
        return $this->credentials();
    }

    /** @return array{project_id: string, client_email: string, private_key: string}|null */
    private function credentials(): ?array
    {
        $path = (string) config('aqdar.notifications.fcm.credentials', '');
        if ($path !== '' && is_readable($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json)) {
                $project = (string) ($json['project_id'] ?? '');
                $email = (string) ($json['client_email'] ?? '');
                $key = (string) ($json['private_key'] ?? '');
                if ($project !== '' && $email !== '' && $key !== '') {
                    return [
                        'project_id' => $project,
                        'client_email' => $email,
                        'private_key' => $this->normalizeKey($key),
                    ];
                }
            }
        }

        $project = (string) config('aqdar.notifications.fcm.project_id', '');
        $email = (string) config('aqdar.notifications.fcm.client_email', '');
        $key = $this->normalizeKey((string) config('aqdar.notifications.fcm.private_key', ''));
        if ($project === '' || $email === '' || $key === '') {
            return null;
        }

        return [
            'project_id' => $project,
            'client_email' => $email,
            'private_key' => $key,
        ];
    }

    /** @param array{project_id: string, client_email: string, private_key: string} $creds */
    private function accessToken(array $creds): string
    {
        $cacheKey = 'aqdar.fcm.access_token.'.$creds['project_id'];
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $jwt = $this->jwt($creds);
        if ($jwt === '') {
            return '';
        }

        $response = Http::asForm()->timeout(12)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);
        $token = (string) $response->json('access_token', '');
        if ($token === '') {
            Log::warning('aqdar.fcm.auth_failed', ['http' => $response->status()]);

            return '';
        }

        $ttl = max(60, ((int) $response->json('expires_in', 3600)) - 60);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /** @param array{project_id: string, client_email: string, private_key: string} $creds */
    private function jwt(array $creds): string
    {
        $now = time();
        $header = $this->b64url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->b64url((string) json_encode([
            'iss' => $creds['client_email'],
            'sub' => $creds['client_email'],
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ]));
        $unsigned = $header.'.'.$claims;
        $ok = openssl_sign($unsigned, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256);
        if (! $ok || ! is_string($signature) || $signature === '') {
            Log::warning('aqdar.fcm.sign_failed');

            return '';
        }

        return $unsigned.'.'.$this->b64url($signature);
    }

    private function normalizeKey(string $key): string
    {
        $key = trim(str_replace(['\\n', "\r"], ["\n", ''], $key));

        return $key;
    }

    private function b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function safeError(string $status): string
    {
        $status = preg_replace('/[^\x20-\x7E]/', '', $status) ?? $status;

        return substr($status !== '' ? $status : 'fcm_failed', 0, 80);
    }
}
