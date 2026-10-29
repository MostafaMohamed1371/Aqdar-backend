<?php

namespace App\Modules\Listing\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ImageProcessor
{
    public function stripAndResize(UploadedFile $file, int $maxWidth = 1600): string
    {
        $image = @imagecreatefromstring($file->getContent());

        if ($image === false) {
            throw ValidationException::withMessages([
                'file' => [__('validation.image', ['attribute' => 'file'])],
            ]);
        }

        $image = $this->orient($image, $file);
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > $maxWidth && $width > 0) {
            $newHeight = max(1, (int) round($height * ($maxWidth / $width)));
            $resized = imagecreatetruecolor($maxWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        return $this->toJpeg($image);
    }

    public function dimensions(string $bytes): array
    {
        $size = @getimagesizefromstring($bytes);

        return $size === false ? [null, null] : [(int) $size[0], (int) $size[1]];
    }

    public function watermark(string $bytes, string $label): string
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return $bytes;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $band = imagecolorallocatealpha($image, 12, 28, 36, 70);
        $text = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, $height - 40, $width, $height, $band);
        imagestring($image, 5, 12, $height - 30, $label, $text);

        return $this->toJpeg($image);
    }

    private function orient(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getPathname());
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    private function toJpeg(\GdImage $image): string
    {
        ob_start();
        imagejpeg($image, quality: 85);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
