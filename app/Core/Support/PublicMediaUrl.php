<?php

namespace App\Core\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicMediaUrl
{
    public static function for(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        $path = ltrim($path, '/');
        $configured = config('aqdar.public_media_base_url');

        if (filled($configured)) {
            return rtrim((string) $configured, '/').'/'.$path;
        }

        $request = request();

        if ($request instanceof Request && filled($request->getHttpHost())) {
            $host = $request->getSchemeAndHttpHost();

            // Phones cannot load http://localhost — fall back to APP_URL when it is a real host.
            if (self::isLoopbackHost($request->getHost())) {
                $appUrl = rtrim((string) config('app.url'), '/');
                $appHost = parse_url($appUrl, PHP_URL_HOST);

                if (filled($appUrl) && filled($appHost) && ! self::isLoopbackHost((string) $appHost)) {
                    return $appUrl.'/storage/media/'.$path;
                }
            }

            return $host.'/storage/media/'.$path;
        }

        return Storage::disk('public_media')->url($path);
    }

    private static function isLoopbackHost(string $host): bool
    {
        $host = strtolower($host);

        return in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true);
    }
}
