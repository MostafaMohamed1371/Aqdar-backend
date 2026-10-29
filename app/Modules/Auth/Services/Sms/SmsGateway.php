<?php

namespace App\Modules\Auth\Services\Sms;

interface SmsGateway
{
    public function send(string $phone, string $message): void;
}
