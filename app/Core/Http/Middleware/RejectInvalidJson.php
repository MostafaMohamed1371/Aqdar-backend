<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectInvalidJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $contentType = strtolower((string) $request->header('Content-Type', ''));

        if (! str_contains($contentType, 'json')) {
            return $next($request);
        }

        $raw = $request->getContent();

        if ($raw === '') {
            return $next($request);
        }

        json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            abort(Response::HTTP_BAD_REQUEST, __('aqdar.http.invalid_json'));
        }

        return $next($request);
    }
}
