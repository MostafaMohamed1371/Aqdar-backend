<?php

namespace App\Modules\Dashboard\Support;

use App\Modules\Dashboard\Events\DeskUpdated;

final class DeskRealtime
{
    /**
     * Notify every signed-in staff desk that a resource changed.
     *
     * @param  list<string>  $screens
     * @param  array<string, mixed>  $meta
     */
    public static function push(string $resource, string $action, ?int $id = null, array $screens = [], array $meta = []): void
    {
        if ((string) config('broadcasting.default') === 'null' || (string) config('broadcasting.default') === '') {
            return;
        }

        $defaultScreens = match ($resource) {
            'listings', 'documents' => ['ADM-01', 'ADM-02', 'ADM-03'],
            'users' => ['ADM-01', 'ADM-04'],
            'staff' => ['ADM-06'],
            'leads', 'support' => ['ADM-01', 'ADM-07'],
            'requests' => ['ADM-01', 'ADM-08'],
            'cases' => ['ADM-01', 'ADM-09'],
            'commissions' => ['ADM-01', 'ADM-10'],
            'content' => ['ADM-11'],
            'settings' => ['ADM-13'],
            'inbox' => ['staff-inbox', 'ADM-01'],
            default => ['ADM-01'],
        };

        event(new DeskUpdated(
            $resource,
            $action,
            $id,
            $screens === [] ? $defaultScreens : $screens,
            $meta,
        ));
    }
}
