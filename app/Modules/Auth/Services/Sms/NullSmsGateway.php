<?php

namespace App\Modules\Auth\Services\Sms;

class NullSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        //
    }
}
