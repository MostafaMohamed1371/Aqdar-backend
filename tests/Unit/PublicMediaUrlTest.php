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
}
