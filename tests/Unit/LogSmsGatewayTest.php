<?php

namespace Tests\Unit;

use App\Modules\Auth\Services\Sms\LogSmsGateway;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogSmsGatewayTest extends TestCase
{
    #[Test]
    public function it_logs_the_recipient_without_the_message_body(): void
    {
        $captured = null;
        Log::listen(function ($event) use (&$captured): void {
            $captured = $event;
        });

        (new LogSmsGateway)->send('+201099988877', 'Your AQDAR code is 123456.');

        $this->assertNotNull($captured);
        $this->assertSame('info', $captured->level);
        $this->assertSame('aqdar.sms.sent', $captured->message);
        $this->assertSame('+201099988877', $captured->context['phone'] ?? null);
        $this->assertSame(['phone', 'length'], array_keys($captured->context));
        $this->assertStringNotContainsString('123456', (string) json_encode($captured->context));
    }
}
