<?php

namespace Tests\Unit;

use App\Core\Identity\ContactMask;
use App\Core\Identity\EgyptianPhone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EgyptianPhoneTest extends TestCase
{
    #[Test]
    public function it_normalizes_local_and_international_numbers(): void
    {
        $this->assertSame('+201012345678', EgyptianPhone::normalize('01012345678'));
        $this->assertSame('+201012345678', EgyptianPhone::normalize('+20 101 234 5678'));
        $this->assertSame('+201012345678', EgyptianPhone::normalize('00201012345678'));
    }

    #[Test]
    public function it_rejects_invalid_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EgyptianPhone::normalize('123');
    }

    #[Test]
    public function it_masks_phone_and_email(): void
    {
        $this->assertSame('010****5678', ContactMask::phone('+201012345678'));
        $this->assertSame('a***@example.com', ContactMask::email('ahmed@example.com'));
    }
}
