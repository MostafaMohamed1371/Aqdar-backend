<?php

namespace App\Core\Broadcasting;

use Illuminate\Support\Facades\Log;
use Pusher\Pusher;
use Throwable;

/**
 * Dispatches broadcast events without failing the request when the driver is misconfigured
 * (for example Pusher package missing on a host still running PHP 8.3).
 */
final class SafeBroadcast
{
    public static function dispatch(object $event): void
    {
        $driver = (string) config('broadcasting.default', 'null');

        if ($driver === 'pusher' && ! class_exists(Pusher::class)) {
            Log::warning('broadcast.pusher_missing', ['event' => $event::class]);

            return;
        }

        try {
            event($event);
        } catch (Throwable $exception) {
            Log::warning('broadcast.failed', [
                'event' => $event::class,
                'driver' => $driver,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
