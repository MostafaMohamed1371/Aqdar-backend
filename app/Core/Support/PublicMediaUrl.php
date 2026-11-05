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
        $request = request();

        if ($request instanceof Request && filled($request->getHttpHost())) {
            return $request->getSchemeAndHttpHost().'/storage/media/'.$path;
        }

        return Storage::disk('public_media')->url($path);
    }
}
