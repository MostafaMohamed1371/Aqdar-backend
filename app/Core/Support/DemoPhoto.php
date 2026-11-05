<?php

namespace App\Core\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

class DemoPhoto
{
    /** @var array<string, string|false> */
    private static array $downloaded = [];

    /**
     * Build a displayable JPEG for seeded listings.
     *
     * Tries a small pool of Picsum photos first so the mobile gallery looks real,
     * then falls back to a generated building scene if the network is down.
     */
    public static function jpeg(string $seed, int $width = 1200, int $height = 800): string
    {
        if (! app()->environment('testing')) {
            $slot = 'aqdar-'.(abs(crc32($seed)) % 8);
            if (! array_key_exists($slot, self::$downloaded)) {
                self::$downloaded[$slot] = self::download($slot, $width, $height) ?? false;
            }

            if (is_string(self::$downloaded[$slot])) {
                return self::$downloaded[$slot];
            }
        }

        return self::paint($seed, $width, $height);
    }

    public static function logo(string $seed, int $size = 256): string
    {
        return self::paintLogo($seed, $size);
    }

    public static function paint(string $seed, int $width = 1200, int $height = 800): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return self::tinyJpeg();
        }

        $image = imagecreatetruecolor($width, $height);
        $hash = abs(crc32($seed));
        $palette = self::palette($hash);

        $ground = imagecolorallocate($image, $palette['ground'][0], $palette['ground'][1], $palette['ground'][2]);
        $building = imagecolorallocate($image, $palette['building'][0], $palette['building'][1], $palette['building'][2]);
        $accent = imagecolorallocate($image, $palette['accent'][0], $palette['accent'][1], $palette['accent'][2]);
        $window = imagecolorallocate($image, $palette['window'][0], $palette['window'][1], $palette['window'][2]);
        $white = imagecolorallocate($image, 255, 255, 255);

        for ($y = 0; $y < (int) ($height * 0.62); $y++) {
            $t = $y / max(1, (int) ($height * 0.62));
            $r = (int) ($palette['sky'][0] + ($palette['horizon'][0] - $palette['sky'][0]) * $t);
            $g = (int) ($palette['sky'][1] + ($palette['horizon'][1] - $palette['sky'][1]) * $t);
            $b = (int) ($palette['sky'][2] + ($palette['horizon'][2] - $palette['sky'][2]) * $t);
            $line = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $line);
        }

        imagefilledrectangle($image, 0, (int) ($height * 0.62), $width, $height, $ground);

        $left = (int) ($width * 0.12);
        $right = (int) ($width * 0.88);
        $top = (int) ($height * (0.18 + ($hash % 8) / 100));
        $base = (int) ($height * 0.78);
        imagefilledrectangle($image, $left, $top, $right, $base, $building);

        $roofY = max(20, $top - (int) ($height * 0.08));
        imagefilledpolygon($image, [
            $left - (int) ($width * 0.04), $top,
            (int) (($left + $right) / 2), $roofY,
            $right + (int) ($width * 0.04), $top,
        ], $accent);

        $cols = 4 + ($hash % 3);
        $rows = 3 + ($hash % 2);
        $padX = (int) (($right - $left) * 0.08);
        $padY = (int) (($base - $top) * 0.12);
        $cellW = (int) (($right - $left - $padX * 2) / $cols);
        $cellH = (int) (($base - $top - $padY * 2) / $rows);

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $x1 = $left + $padX + $col * $cellW + 8;
                $y1 = $top + $padY + $row * $cellH + 8;
                $x2 = $x1 + $cellW - 16;
                $y2 = $y1 + $cellH - 16;
                imagefilledrectangle($image, $x1, $y1, $x2, $y2, $window);
            }
        }

        $doorW = (int) ($width * 0.08);
        $doorH = (int) ($height * 0.16);
        $doorX = (int) (($left + $right) / 2 - $doorW / 2);
        imagefilledrectangle($image, $doorX, $base - $doorH, $doorX + $doorW, $base, $accent);

        imagestring($image, 5, (int) ($width * 0.06), (int) ($height * 0.90), 'AQDAR '.$seed, $white);

        ob_start();
        imagejpeg($image, null, 84);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    private static function paintLogo(string $seed, int $size): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return self::tinyJpeg();
        }

        $image = imagecreatetruecolor($size, $size);
        $hash = abs(crc32($seed));
        $palette = self::palette($hash);
        $bg = imagecolorallocate($image, $palette['building'][0], $palette['building'][1], $palette['building'][2]);
        $fg = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $size, $size, $bg);

        $label = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $seed) ?: 'AQ', 0, 3));
        imagestring($image, 5, (int) ($size * 0.28), (int) ($size * 0.42), $label, $fg);

        ob_start();
        imagejpeg($image, null, 86);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * @return array{
     *     sky: array{0: int, 1: int, 2: int},
     *     horizon: array{0: int, 1: int, 2: int},
     *     ground: array{0: int, 1: int, 2: int},
     *     building: array{0: int, 1: int, 2: int},
     *     accent: array{0: int, 1: int, 2: int},
     *     window: array{0: int, 1: int, 2: int}
     * }
     */
    private static function palette(int $hash): array
    {
        $sets = [
            ['sky' => [90, 160, 210], 'horizon' => [245, 210, 160], 'ground' => [62, 92, 58], 'building' => [196, 154, 108], 'accent' => [140, 72, 48], 'window' => [230, 236, 242]],
            ['sky' => [70, 130, 180], 'horizon' => [255, 186, 120], 'ground' => [90, 78, 62], 'building' => [176, 126, 92], 'accent' => [92, 58, 42], 'window' => [214, 228, 236]],
            ['sky' => [120, 176, 204], 'horizon' => [250, 224, 180], 'ground' => [74, 96, 64], 'building' => [210, 186, 154], 'accent' => [48, 92, 110], 'window' => [236, 242, 246]],
            ['sky' => [64, 112, 168], 'horizon' => [232, 168, 112], 'ground' => [86, 70, 52], 'building' => [154, 110, 86], 'accent' => [176, 96, 48], 'window' => [208, 220, 228]],
        ];

        return $sets[$hash % count($sets)];
    }

    private static function download(string $seed, int $width, int $height): ?string
    {
        try {
            $response = Http::timeout(8)
                ->withOptions(['allow_redirects' => true])
                ->get('https://picsum.photos/seed/'.rawurlencode($seed).'/'.$width.'/'.$height.'.jpg');

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();
            if (strlen($body) < 1024 || ! str_starts_with($body, "\xFF\xD8")) {
                return null;
            }

            return $body;
        } catch (Throwable) {
            return null;
        }
    }

    private static function tinyJpeg(): string
    {
        return (string) base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAQUCf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Bf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Bf//Z', true);
    }
}
