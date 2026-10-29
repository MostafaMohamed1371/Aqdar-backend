<?php

namespace Tests\Unit;

use App\Modules\Auth\Services\OtpService;
use App\Modules\Auth\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    #[Test]
    public function it_dispatches_sms_to_the_gateway(): void
    {
        $gateway = new class implements SmsGateway
        {
            /** @var list<array{phone: string, message: string}> */
            public array $sent = [];

            public function send(string $phone, string $message): void
            {
                $this->sent[] = ['phone' => $phone, 'message' => $message];
            }
        };
        $this->app->instance(SmsGateway::class, $gateway);
        $this->app->forgetInstance(OtpService::class);

        app(OtpService::class)->send('01012345678');

        $this->assertCount(1, $gateway->sent);
        $this->assertSame('+201012345678', $gateway->sent[0]['phone']);
        $this->assertStringContainsString('123456', $gateway->sent[0]['message']);
    }

    #[Test]
    public function it_accepts_the_testing_code_after_send(): void
    {
        $otp = app(OtpService::class);
        $otp->send('01012345678');

        $this->assertSame('+201012345678', $otp->verify('01012345678', '123456'));
    }

    #[Test]
    public function it_rejects_a_wrong_code(): void
    {
        $otp = app(OtpService::class);
        $otp->send('01012345678');

        $this->expectException(ValidationException::class);

        $otp->verify('01012345678', '000000');
    }

    #[Test]
    public function it_stores_a_hash_not_the_plain_code(): void
    {
        app(OtpService::class)->send('01012345678');

        $stored = Cache::get('otp:code:+201012345678');

        $this->assertIsString($stored);
        $this->assertNotSame('123456', $stored);
    }

    #[Test]
    public function it_rejects_the_same_code_after_a_successful_verify(): void
    {
        $otp = app(OtpService::class);
        $otp->send('01012345678');
        $otp->verify('01012345678', '123456');

        $this->expectException(ValidationException::class);

        $otp->verify('01012345678', '123456');
    }
}
