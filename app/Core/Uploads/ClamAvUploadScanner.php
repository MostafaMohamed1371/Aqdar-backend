<?php

namespace App\Core\Uploads;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

class ClamAvUploadScanner implements UploadScanner
{
    public function __construct(private MimeUploadScanner $mime) {}

    public function assertSafe(UploadedFile $file, array $allowedExtensions): void
    {
        $this->mime->assertSafe($file, $allowedExtensions);

        $binary = (string) config('aqdar.files.scan.clamav_binary', 'clamdscan');
        if (! $this->binaryAvailable($binary)) {
            Log::warning('aqdar.upload.clamav_unavailable', ['binary' => $binary]);

            return;
        }

        $path = $file->getRealPath();
        if (! is_string($path) || $path === '') {
            return;
        }

        $result = Process::timeout(30)->run([$binary, '--no-summary', $path]);
        $output = $result->output().$result->errorOutput();

        if ($result->exitCode() === 1 || str_contains($output, 'FOUND')) {
            throw ValidationException::withMessages([
                'file' => [__('aqdar.uploads.malware')],
            ]);
        }

        if ($result->exitCode() > 1) {
            Log::warning('aqdar.upload.clamav_error', [
                'binary' => $binary,
                'exit' => $result->exitCode(),
            ]);
        }
    }

    private function binaryAvailable(string $binary): bool
    {
        if ($binary === '') {
            return false;
        }

        if (str_contains($binary, '/') || str_contains($binary, '\\')) {
            return is_executable($binary);
        }

        $which = Process::run(['which', $binary]);

        return $which->successful() && filled(trim($which->output()));
    }
}
