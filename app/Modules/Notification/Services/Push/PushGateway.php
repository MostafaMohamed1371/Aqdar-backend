<?php

namespace App\Modules\Notification\Services\Push;

interface PushGateway
{
    /** @param array<string, mixed> $payload */
    public function send(string $token, array $payload): PushResult;
}
