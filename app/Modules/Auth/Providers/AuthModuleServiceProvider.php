<?php

namespace App\Modules\Auth\Providers;

use App\Core\Module\ModuleServiceProvider;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\OtpService;
use App\Modules\Auth\Services\Sms\HttpSmsGateway;
use App\Modules\Auth\Services\Sms\LogSmsGateway;
use App\Modules\Auth\Services\Sms\NullSmsGateway;
use App\Modules\Auth\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Route;

class AuthModuleServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Auth';
    }

    public function register(): void
    {
        $this->app->singleton(OtpService::class);
        $this->app->singleton(AuthService::class);
        $this->app->bind(SmsGateway::class, function (): SmsGateway {
            return match ((string) config('aqdar.sms.driver', 'log')) {
                'null' => new NullSmsGateway,
                'http' => new HttpSmsGateway,
                default => new LogSmsGateway,
            };
        });
    }

    public function boot(): void
    {
        $this->loadAuthWebRoutes();
        $this->loadMigrations();
        $this->loadViews();

        $apiRoutes = $this->modulePath('Routes/api.php');

        if (is_file($apiRoutes)) {
            Route::middleware('api')
                ->prefix('api/'.config('structer.api_prefix', 'v1'))
                ->group($apiRoutes);
        }
    }

    protected function loadRoutes(): void
    {
        //
    }

    protected function loadAuthWebRoutes(): void
    {
        $webRoutes = $this->modulePath('Routes/web.php');

        if (is_file($webRoutes)) {
            Route::middleware('web')->group($webRoutes);
        }
    }
}
