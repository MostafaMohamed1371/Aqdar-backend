<?php

namespace App\Core\Uploads;

use Illuminate\Http\UploadedFile;

interface UploadScanner
{
    /**
     * Reject malware signatures and MIME/extension mismatches (NFR-07).
     *
     * @param  list<string>  $allowedExtensions
     */
    public function assertSafe(UploadedFile $file, array $allowedExtensions): void;
}
