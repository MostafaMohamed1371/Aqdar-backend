<?php

namespace App\Modules\Notification\Services\Push;

class PushResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $response,
        public readonly bool $tokenExpired = false,
    ) {}
}
