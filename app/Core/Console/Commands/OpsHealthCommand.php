<?php

namespace App\Core\Console\Commands;

use App\Core\Enums\DeliveryStatus;
use App\Core\Enums\NotificationChannel;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Setting\Services\SettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OpsHealthCommand extends Command
{
    protected $signature = 'aqdar:ops-health';

    protected $description = 'Print failed jobs, failed push deliveries, and maintenance state';

    public function handle(SettingService $settings): int
    {
        $failedJobs = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
        $failedPushes = NotificationDelivery::query()
            ->where('channel', NotificationChannel::Push)
            ->where('status', DeliveryStatus::Failed)
            ->count();

        $this->table(['metric', 'value'], [
            ['failed_jobs', $failedJobs],
            ['failed_pushes', $failedPushes],
            ['maintenance_mode', $settings->boolValue('maintenance_mode', false) ? '1' : '0'],
            ['queue', (string) config('queue.default')],
        ]);

        return $failedJobs + $failedPushes > 0 ? self::FAILURE : self::SUCCESS;
    }
}
