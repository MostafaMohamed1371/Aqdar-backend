<?php

namespace App\Core\Http\Middleware;

use App\Modules\Setting\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMarketplaceOpen
{
    public function __construct(private SettingService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/v1/config') || $request->is('api/v1/config/*')) {
            return $next($request);
        }

        if (! $this->settings->boolValue('maintenance_mode', false)) {
            return $next($request);
        }

        abort(503, __('aqdar.http.maintenance'));
    }
}
