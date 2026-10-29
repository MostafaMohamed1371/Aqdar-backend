<?php

use App\Modules\Notification\Jobs\DispatchOverdueFollowupsJob;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('aqdar:notify-overdue-followups', function (NotificationService $notifications) {
    $count = $notifications->notifyOverdueFollowups();
    $this->info('overdue_notified='.$count);
})->purpose('Notify assigned Sales of overdue follow-ups');

Artisan::command('aqdar:dispatch-broadcasts', function (NotificationService $notifications) {
    $count = $notifications->dispatchDueBroadcasts();
    $this->info('broadcasts_queued='.$count);
})->purpose('Dispatch due marketing broadcasts');

Schedule::job(new DispatchOverdueFollowupsJob)->hourly();
Schedule::command('aqdar:dispatch-broadcasts')->everyFifteenMinutes();
Schedule::command('aqdar:prune-audit')->daily();
Schedule::command('aqdar:backup-check')->daily();
Schedule::command('aqdar:backup')->dailyAt('02:15');
