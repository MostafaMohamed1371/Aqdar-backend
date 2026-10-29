<?php

namespace App\Modules\Notification\Providers;

use App\Core\Module\ModuleServiceProvider;
use App\Modules\Listing\Services\ContactLeakScanner;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Services\Push\FcmPushGateway;
use App\Modules\Notification\Services\Push\LogPushGateway;
use App\Modules\Notification\Services\Push\NullPushGateway;
use App\Modules\Notification\Services\Push\PushGateway;

class NotificationModuleServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Notification';
    }

    public function register(): void
    {
        $this->app->singleton(NotificationService::class);
        $this->app->bind(PushGateway::class, function (): PushGateway {
            $driver = (string) config('aqdar.notifications.push_driver', 'log');

            return match ($driver) {
                'null' => new NullPushGateway,
                'fcm' => new FcmPushGateway($this->app->make(ContactLeakScanner::class)),
                default => new LogPushGateway,
            };
        });
    }
}
