<?php

use App\Core\Http\ApiErrorResponse;
use App\Core\Http\Middleware\EnsureMarketplaceOpen;
use App\Core\Http\Middleware\EnsureStaffActive;
use App\Core\Http\Middleware\EnsureStaffPermission;
use App\Core\Http\Middleware\EnsureUserNotSuspended;
use App\Core\Http\Middleware\ForceHttps;
use App\Core\Http\Middleware\OptionalSanctumAuth;
use App\Core\Http\Middleware\RejectInvalidJson;
use App\Core\Http\Middleware\SecurityHeaders;
use App\Core\Http\Middleware\SetLocaleFromRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));

        $middleware->web(prepend: [
            ForceHttps::class,
        ]);

        $middleware->web(append: [
            SetLocaleFromRequest::class,
            EnsureStaffActive::class,
            SecurityHeaders::class,
        ]);

        $middleware->api(prepend: [
            ForceHttps::class,
            SetLocaleFromRequest::class,
            RejectInvalidJson::class,
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
            EnsureMarketplaceOpen::class,
        ]);

        $middleware->throttleApi();

        $middleware->alias([
            'staff.can' => EnsureStaffPermission::class,
            'marketplace.active' => EnsureUserNotSuspended::class,
            'marketplace.open' => EnsureMarketplaceOpen::class,
            'optional.sanctum' => OptionalSanctumAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if ($exception instanceof HttpResponseException) {
                return null;
            }

            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiErrorResponse::from($exception);
        });
    })->create();
