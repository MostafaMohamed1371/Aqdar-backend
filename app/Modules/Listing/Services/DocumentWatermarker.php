<?php

namespace App\Modules\Listing\Services;

class DocumentWatermarker
{
    public function __construct(
        private ImageProcessor $images,
        private PdfWatermarker $pdfs,
    ) {}

    public function stamp(string $bytes, string $mime, string $label): string
    {
        $mime = strtolower(trim($mime));

        if (str_starts_with(ltrim($bytes), '%PDF') || $mime === 'application/pdf') {
            return $this->pdfs->stamp($bytes, $label);
        }

        if (str_starts_with($mime, 'image/')) {
            return $this->images->watermark($bytes, $label);
        }

        return $bytes;
    }
}
