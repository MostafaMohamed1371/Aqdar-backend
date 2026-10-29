<?php

namespace App\Modules\Notification\Services\Push;

use Illuminate\Support\Facades\Log;

class LogPushGateway implements PushGateway
{
    public function send(string $token, array $payload): PushResult
    {
        if ($token === '' || str_contains(strtolower($token), 'expired')) {
            return new PushResult(false, 'token expired', true);
        }

        Log::info('aqdar.push.queued', [
            'keys' => array_keys($payload),
        ]);

        return new PushResult(true, '200 OK');
    }
}
