<?php

namespace App\Modules\Notification\Jobs;

use App\Modules\Notification\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchOverdueFollowupsJob implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $notifications): void
    {
        $notifications->notifyOverdueFollowups();
    }
}
