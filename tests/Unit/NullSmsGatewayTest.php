<?php

namespace Tests\Unit;

use App\Modules\Auth\Services\Sms\NullSmsGateway;
use App\Modules\Auth\Services\Sms\SmsGateway;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NullSmsGatewayTest extends TestCase
{
    #[Test]
    public function it_accepts_a_send_without_throwing(): void
    {
        $gateway = new NullSmsGateway;

        $this->assertInstanceOf(SmsGateway::class, $gateway);
        $gateway->send('+201099988877', 'Your AQDAR code is 123456.');
    }
}
