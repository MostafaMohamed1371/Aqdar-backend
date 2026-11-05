<?php

namespace Tests\Unit;

use App\Core\Support\DemoPhoto;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoPhotoTest extends TestCase
{
    #[Test]
    public function it_paints_a_real_sized_jpeg(): void
    {
        $bytes = DemoPhoto::paint('AQ-1001-1');

        $this->assertStringStartsWith("\xFF\xD8", $bytes);
        $info = getimagesizefromstring($bytes);
        $this->assertIsArray($info);
        $this->assertSame(1200, $info[0]);
        $this->assertSame(800, $info[1]);
        $this->assertSame('image/jpeg', $info['mime']);
    }
}
