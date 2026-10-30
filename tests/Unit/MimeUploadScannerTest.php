<?php

namespace Tests\Unit;

use App\Core\Uploads\MimeUploadScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MimeUploadScannerTest extends TestCase
{
    public function test_pdf_magic_is_accepted(): void
    {
        $file = UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n".str_repeat('x', 64));

        app(MimeUploadScanner::class)->assertSafe($file, ['pdf', 'jpg']);

        $this->assertTrue(true);
    }

    public function test_exe_magic_named_pdf_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('id.pdf', "MZ\x90".str_repeat("\0", 64));

        try {
            app(MimeUploadScanner::class)->assertSafe($file, ['pdf']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
    }

    public function test_png_magic_named_pdf_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'id.pdf',
            "\x89PNG\r\n\x1A\n".str_repeat('x', 32),
        );

        try {
            app(MimeUploadScanner::class)->assertSafe($file, ['pdf']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('payload.exe', "MZ\x90".str_repeat('A', 32));

        try {
            app(MimeUploadScanner::class)->assertSafe($file, ['pdf', 'jpg']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
    }
}
