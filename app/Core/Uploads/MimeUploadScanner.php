<?php

namespace App\Core\Uploads;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class MimeUploadScanner implements UploadScanner
{
    /** @var array<string, list<string>> */
    private const MIME_EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'video/mp4' => ['mp4'],
        'video/webm' => ['webm'],
    ];

    public function assertSafe(UploadedFile $file, array $allowedExtensions): void
    {
        $allowed = array_values(array_unique(array_map('strtolower', $allowedExtensions)));
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension === '' || ! in_array($extension, $allowed, true)) {
            $this->reject('uploads.rejected');
        }

        $header = $this->header($file);
        if ($this->isDangerous($header)) {
            $this->reject('uploads.malware');
        }

        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: ''));
        $mimeFamily = $this->extensionsForMime($mime);
        if ($mimeFamily !== [] && ! in_array($extension, $mimeFamily, true)) {
            $this->reject('uploads.type_mismatch');
        }

        $detected = $this->detectedExtension($header);
        if ($detected !== null && ! $this->sameFamily($detected, $extension)) {
            $this->reject('uploads.type_mismatch');
        }
    }

    private function header(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return (string) $file->getContent();
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        $header = (string) fread($handle, 32);
        fclose($handle);

        return $header;
    }

    private function isDangerous(string $header): bool
    {
        return str_starts_with($header, 'MZ')
            || str_starts_with($header, "\x7FELF")
            || str_starts_with($header, "\xCA\xFE\xBA\xBE")
            || str_starts_with($header, "\xCF\xFA\xED\xFE")
            || str_starts_with($header, "\xCE\xFA\xED\xFE");
    }

    private function detectedExtension(string $header): ?string
    {
        if (str_starts_with($header, '%PDF')) {
            return 'pdf';
        }
        if (strlen($header) >= 3 && $header[0] === "\xFF" && $header[1] === "\xD8" && $header[2] === "\xFF") {
            return 'jpg';
        }
        if (str_starts_with($header, "\x89PNG")) {
            return 'png';
        }
        if (str_starts_with($header, 'RIFF') && str_contains(substr($header, 0, 16), 'WEBP')) {
            return 'webp';
        }
        if (strlen($header) >= 8 && substr($header, 4, 4) === 'ftyp') {
            return 'mp4';
        }
        if (str_starts_with($header, "\x1A\x45\xDF\xA3")) {
            return 'webm';
        }

        return null;
    }

    /** @return list<string> */
    private function extensionsForMime(string $mime): array
    {
        return self::MIME_EXTENSIONS[$mime] ?? [];
    }

    private function sameFamily(string $detected, string $extension): bool
    {
        if ($detected === 'jpg') {
            return in_array($extension, ['jpg', 'jpeg'], true);
        }

        return $detected === $extension;
    }

    private function reject(string $key): never
    {
        throw ValidationException::withMessages([
            'file' => [__('aqdar.'.$key)],
        ]);
    }
}
