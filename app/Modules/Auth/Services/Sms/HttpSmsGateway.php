<?php

namespace App\Modules\Auth\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        $url = (string) config('aqdar.sms.http.url');

        if ($url === '') {
            throw new RuntimeException('SMS HTTP URL is not configured.');
        }

        $toField = (string) config('aqdar.sms.http.to_field', 'to');
        $messageField = (string) config('aqdar.sms.http.message_field', 'message');
        $token = (string) config('aqdar.sms.http.token');

        $request = Http::timeout(10)->acceptJson();

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->post($url, [
            $toField => $phone,
            $messageField => $message,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('SMS provider returned HTTP '.$response->status());
        }
    }
}
