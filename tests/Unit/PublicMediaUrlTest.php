<?php

namespace Tests\Unit;

use App\Core\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicMediaUrlTest extends TestCase
{
    #[Test]
    public function it_uses_the_incoming_request_host(): void
    {
        $this->app->instance('request', Request::create('http://10.0.2.2:8000/api/v1/listings', 'GET'));

        $this->assertSame(
            'http://10.0.2.2:8000/storage/media/listings/1/demo-1.jpg',
            PublicMediaUrl::for('listings/1/demo-1.jpg'),
        );
    }

    #[Test]
    public function it_returns_null_when_the_path_is_empty(): void
    {
        $this->assertNull(PublicMediaUrl::for(null));
        $this->assertNull(PublicMediaUrl::for(''));
    }

    #[Test]
    public function it_prefers_configured_public_media_base_url(): void
    {
        config(['aqdar.public_media_base_url' => 'https://cdn.example.com/storage/media']);
        $this->app->instance('request', Request::create('http://127.0.0.1:8000/api/v1/content/banners', 'GET'));

        $this->assertSame(
            'https://cdn.example.com/storage/media/banners/hero.jpg',
            PublicMediaUrl::for('banners/hero.jpg'),
        );
    }

    #[Test]
    public function it_falls_back_from_loopback_to_app_url_when_app_url_is_public(): void
    {
        config([
            'aqdar.public_media_base_url' => null,
            'app.url' => 'https://aqdar.example.com',
        ]);
        $this->app->instance('request', Request::create('http://127.0.0.1:8000/api/v1/content/banners', 'GET'));

        $this->assertSame(
            'https://aqdar.example.com/storage/media/banners/hero.jpg',
            PublicMediaUrl::for('banners/hero.jpg'),
        );
    }

    #[Test]
    public function it_detects_when_mobile_would_hit_loopback(): void
    {
        config([
            'aqdar.public_media_base_url' => null,
            'app.url' => 'http://localhost:8000',
        ]);
        $this->app->instance('request', Request::create('http://127.0.0.1:8000/api/v1/content/banners', 'GET'));

        $this->assertTrue(PublicMediaUrl::mobileWouldHitLoopback());

        config(['aqdar.public_media_base_url' => 'https://cdn.example.com/storage/media']);

        $this->assertFalse(PublicMediaUrl::mobileWouldHitLoopback());
    }
}
