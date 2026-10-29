<?php

namespace App\Modules\Auth\Services\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        Log::info('aqdar.sms.sent', [
            'phone' => $phone,
            'length' => strlen($message),
        ]);
    }
}
