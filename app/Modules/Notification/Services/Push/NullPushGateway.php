<?php

namespace App\Modules\Notification\Services\Push;

class NullPushGateway implements PushGateway
{
    public function send(string $token, array $payload): PushResult
    {
        return new PushResult(true, 'skipped', false);
    }
}
