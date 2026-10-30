<?php

namespace App\Providers;

use App\Core\Audit\AuditLogger;
use App\Core\Console\Commands\BackupCheckCommand;
use App\Core\Console\Commands\BackupCommand;
use App\Core\Console\Commands\JmeterPrepareCommand;
use App\Core\Console\Commands\MakeModuleCommand;
use App\Core\Console\Commands\OpenApiCommand;
use App\Core\Console\Commands\OpsHealthCommand;
use App\Core\Console\Commands\PruneAuditCommand;
use App\Core\Console\Commands\QaRunCommand;
use App\Core\Console\Commands\ScalingCheckCommand;
use App\Core\Console\Commands\SecurityCheckCommand;
use App\Core\Identity\PublicCodeGenerator;
use App\Core\Uploads\ClamAvUploadScanner;
use App\Core\Uploads\MimeUploadScanner;
use App\Core\Uploads\UploadScanner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(PublicCodeGenerator::class);
        $this->app->singleton(MimeUploadScanner::class);
        $this->app->singleton(UploadScanner::class, function ($app) {
            $mime = $app->make(MimeUploadScanner::class);
            if (config('aqdar.files.scan.driver') === 'clamav') {
                return new ClamAvUploadScanner($mime);
            }

            return $mime;
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                JmeterPrepareCommand::class,
                MakeModuleCommand::class,
                SecurityCheckCommand::class,
                QaRunCommand::class,
                ScalingCheckCommand::class,
                PruneAuditCommand::class,
                OpsHealthCommand::class,
                BackupCheckCommand::class,
                BackupCommand::class,
                OpenApiCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinutes(
                config('structer.security.login_decay_minutes', 1),
                config('structer.security.login_max_attempts', 5),
            )->by($email.'|'.$request->ip());
        });

        RateLimiter::for('api-auth', function (Request $request): Limit {
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinutes(
                config('structer.security.api_auth_decay_minutes', 1),
                config('structer.security.api_auth_max_attempts', 10),
            )->by($email.'|'.$request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinutes(
                config('structer.security.password_reset_decay_minutes', 1),
                config('structer.security.password_reset_max_attempts', 3),
            )->by($email.'|'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request): Limit {
            if (config('structer.jmeter.load_test')) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('search', function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('otp', function (Request $request): Limit {
            $phone = preg_replace('/\D+/', '', (string) $request->input('phone'));

            return Limit::perMinutes(
                config('aqdar.otp.decay_minutes', 10),
                config('aqdar.otp.max_attempts', 5),
            )->by($phone.'|'.$request->ip());
        });
    }
}
