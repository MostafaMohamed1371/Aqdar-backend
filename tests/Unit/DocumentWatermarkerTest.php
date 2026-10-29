<?php

namespace Tests\Unit;

use App\Modules\Listing\Services\DocumentWatermarker;
use App\Modules\Listing\Services\ImageProcessor;
use App\Modules\Listing\Services\PdfWatermarker;
use Tests\TestCase;

class DocumentWatermarkerTest extends TestCase
{
    public function test_pdf_stamp_keeps_original_page_and_adds_reviewer_label(): void
    {
        $pdfs = new PdfWatermarker;
        $original = $pdfs->minimal('Contract page');
        $stamped = $pdfs->stamp($original, 'AQDAR staff-9 2026-10-29 13:43');

        $this->assertStringContainsString('%PDF', $stamped);
        $this->assertStringContainsString('Contract page', $stamped);
        $this->assertStringContainsString('AQDAR staff-9 2026-10-29 13:43', $stamped);
        $this->assertStringContainsString('/AqWm', $stamped);
    }

    public function test_unreadable_pdf_still_downloads_as_watermarked_pdf(): void
    {
        $stamped = (new PdfWatermarker)->stamp('%PDF-1.4 test', 'AQDAR staff-4 2026-10-29 13:43');

        $this->assertStringStartsWith('%PDF', $stamped);
        $this->assertStringContainsString('AQDAR staff-4 2026-10-29 13:43', $stamped);
    }

    public function test_image_bytes_are_stamped_as_jpeg(): void
    {
        $image = imagecreatetruecolor(80, 40);
        imagefilledrectangle($image, 0, 0, 79, 39, imagecolorallocate($image, 200, 200, 200));
        ob_start();
        imagejpeg($image, quality: 80);
        imagedestroy($image);
        $bytes = (string) ob_get_clean();

        $stamped = (new DocumentWatermarker(new ImageProcessor, new PdfWatermarker))
            ->stamp($bytes, 'image/jpeg', 'AQDAR staff-2 2026-10-29 13:43');

        $info = getimagesizefromstring($stamped);
        $this->assertNotFalse($info);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
    }
}
