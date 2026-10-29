<?php

namespace App\Modules\Notification\Jobs;

use App\Modules\Notification\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId) {}

    public function handle(NotificationService $notifications): void
    {
        $notifications->attemptPush($this->deliveryId);
    }
}
