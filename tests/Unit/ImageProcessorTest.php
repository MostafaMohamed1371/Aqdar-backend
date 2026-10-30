<?php

namespace Tests\Unit;

use App\Modules\Listing\Services\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImageProcessorTest extends TestCase
{
    public function test_public_derivative_drops_exif_and_gps_metadata(): void
    {
        $marker = 'GPSLatitude 30.0444 owner-phone 01012345678';
        $bytes = $this->jpegWithExifSegment($marker);
        $this->assertStringContainsString($marker, $bytes);

        $path = tempnam(sys_get_temp_dir(), 'aqdar-exif');
        file_put_contents($path, $bytes);
        $file = new UploadedFile($path, 'IMG_20260101_owner.jpg', 'image/jpeg', null, true);

        $clean = (new ImageProcessor)->stripAndResize($file);
        @unlink($path);

        $this->assertStringNotContainsString('Exif', $clean);
        $this->assertStringNotContainsString($marker, $clean);
        $info = getimagesizefromstring($clean);
        $this->assertNotFalse($info);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
    }

    public function test_wide_image_is_resized_to_max_width(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'aqdar-wide');
        file_put_contents($path, $this->jpeg(2400, 1200));
        $file = new UploadedFile($path, 'wide.jpg', 'image/jpeg', null, true);

        $clean = (new ImageProcessor)->stripAndResize($file, 1600);
        @unlink($path);

        $this->assertSame([1600, 800], (new ImageProcessor)->dimensions($clean));
    }

    private function jpegWithExifSegment(string $payload): string
    {
        $jpeg = $this->jpeg(64, 48);
        $segment = "Exif\0\0".$payload;
        $app1 = "\xFF\xE1".pack('n', strlen($segment) + 2).$segment;

        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }

    private function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 8, 126, 130));
        ob_start();
        imagejpeg($image, quality: 80);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
