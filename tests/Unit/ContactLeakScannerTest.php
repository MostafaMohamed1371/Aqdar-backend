<?php

namespace Tests\Unit;

use App\Modules\Listing\Services\ContactLeakScanner;
use Tests\TestCase;

class ContactLeakScannerTest extends TestCase
{
    public function test_detects_phone_email_url_whatsapp_and_qr(): void
    {
        $scanner = app(ContactLeakScanner::class);

        $this->assertSame('phone', $scanner->scan('للتواصل 01012345678'));
        $this->assertSame('email', $scanner->scan('email me at owner@example.com'));
        $this->assertSame('url', $scanner->scan('شاهد التفاصيل على https://example.com/unit'));
        $this->assertSame('url', $scanner->scan('راسلني على واتساب بدون رقم'));
        $this->assertSame('qr', $scanner->scan('امسح qr code للتفاصيل'));
        $this->assertSame('qr', $scanner->scan('باركود الوحدة داخل الصورة'));
        $this->assertNull($scanner->scan('وحدة إعادة بيع في مدينتي بدون بيانات تواصل.'));
    }
}
