<?php

namespace Tests\Unit;

use App\Modules\Auth\Services\Sms\HttpSmsGateway;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class HttpSmsGatewayTest extends TestCase
{
    #[Test]
    public function it_posts_to_the_configured_provider(): void
    {
        config([
            'aqdar.sms.http.url' => 'https://sms.example.test/send',
            'aqdar.sms.http.token' => 'secret-token',
            'aqdar.sms.http.to_field' => 'to',
            'aqdar.sms.http.message_field' => 'message',
        ]);

        Http::fake([
            'https://sms.example.test/send' => Http::response(['ok' => true], 200),
        ]);

        (new HttpSmsGateway)->send('+201012345678', 'Your AQDAR code is 123456.');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://sms.example.test/send'
                && $request->hasHeader('Authorization', 'Bearer secret-token')
                && $request['to'] === '+201012345678'
                && $request['message'] === 'Your AQDAR code is 123456.';
        });
    }

    #[Test]
    public function it_fails_when_the_url_is_missing(): void
    {
        config(['aqdar.sms.http.url' => '']);

        $this->expectException(RuntimeException::class);

        (new HttpSmsGateway)->send('+201012345678', 'code');
    }

    #[Test]
    public function it_fails_when_the_provider_returns_http_error(): void
    {
        config(['aqdar.sms.http.url' => 'https://sms.example.test/send']);

        Http::fake([
            'https://sms.example.test/send' => Http::response('fail', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMS provider returned HTTP 500');

        (new HttpSmsGateway)->send('+201012345678', 'code');
    }
}
