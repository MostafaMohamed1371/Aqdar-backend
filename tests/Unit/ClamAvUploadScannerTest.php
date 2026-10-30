<?php

namespace Tests\Unit;

use App\Core\Uploads\ClamAvUploadScanner;
use App\Core\Uploads\MimeUploadScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClamAvUploadScannerTest extends TestCase
{
    public function test_missing_binary_falls_back_to_mime_check(): void
    {
        Log::spy();
        config(['aqdar.files.scan.clamav_binary' => '/tmp/aqdar-missing-clamdscan']);
        $file = UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n".str_repeat('x', 64));

        app(ClamAvUploadScanner::class)->assertSafe($file, ['pdf']);

        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'aqdar.upload.clamav_unavailable');
    }

    public function test_missing_binary_still_rejects_exe_magic(): void
    {
        config(['aqdar.files.scan.clamav_binary' => '/tmp/aqdar-missing-clamdscan']);
        $file = UploadedFile::fake()->createWithContent('id.pdf', "MZ\x90".str_repeat("\0", 64));

        try {
            app(ClamAvUploadScanner::class)->assertSafe($file, ['pdf']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
    }

    public function test_infected_scan_is_rejected(): void
    {
        config(['aqdar.files.scan.clamav_binary' => 'clamdscan']);
        Process::fake([
            '*' => Process::sequence([
                Process::result("/usr/bin/clamdscan\n"),
                Process::result('stream: Win.Test.EICAR FOUND', '', 1),
            ]),
        ]);
        $file = UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n".str_repeat('x', 64));

        try {
            (new ClamAvUploadScanner(app(MimeUploadScanner::class)))->assertSafe($file, ['pdf']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
    }
}
