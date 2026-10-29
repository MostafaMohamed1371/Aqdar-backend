<?php

namespace App\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

class SecurityCheckCommand extends Command
{
    protected $signature = 'structer:security-check {--audit : Run composer audit as part of the check}';

    protected $description = 'Run a security checklist across the Structer application';

    /** @var array<int, array{check: string, status: string, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->components->info('Structer security check');
        $this->newLine();

        $this->checkCsrfInForms();
        $this->checkRateLimiters();
        $this->checkAuthRouteThrottling();
        $this->checkPasswordHashing();
        $this->checkDebugMode();
        $this->checkAppKey();
        $this->checkSensitiveFileProtection();
        $this->checkSecurityMiddleware();
        $this->checkFormRequestValidation();
        $this->checkBladeEscaping();
        $this->checkEloquentOnly();
        $this->checkSqlInjectionPatterns();
        $this->checkUsersApiProtected();
        $this->checkEnvGitignored();

        if ($this->option('audit')) {
            $this->runComposerAudit();
        }

        $this->renderTable();

        $failed = collect($this->results)->where('status', 'FAIL')->count();

        if ($failed > 0) {
            $this->newLine();
            $this->components->error("Security check failed ({$failed} issue(s)).");

            return self::FAILURE;
        }

        $warnings = collect($this->results)->where('status', 'WARN')->count();

        $this->newLine();
        $this->components->info($warnings > 0
            ? "Security check passed with {$warnings} warning(s)."
            : 'Security check passed.');

        return self::SUCCESS;
    }

    private function checkCsrfInForms(): void
    {
        $viewsPath = app_path('Modules');
        $forms = 0;
        $missing = [];

        foreach (File::allFiles($viewsPath) as $file) {
            if ($file->getExtension() !== 'php' || ! str_contains($file->getPathname(), 'views')) {
                continue;
            }

            $contents = File::get($file->getPathname());

            if (! preg_match('/method\s*=\s*["\']POST["\']/i', $contents)) {
                continue;
            }

            $forms++;
            $relative = str_replace(base_path().'/', '', $file->getPathname());

            if (! str_contains($contents, '@csrf')) {
                $missing[] = $relative;
            }
        }

        $this->record(
            'CSRF tokens in HTML forms',
            $missing === [] ? 'PASS' : 'FAIL',
            $missing === []
                ? "{$forms} POST form(s) include @csrf"
                : 'Missing @csrf in: '.implode(', ', $missing),
        );
    }

    private function checkRateLimiters(): void
    {
        $required = ['login', 'api-auth', 'password-reset', 'api'];
        $missing = [];

        foreach ($required as $name) {
            if (! RateLimiter::limiter($name)) {
                $missing[] = $name;
            }
        }

        $this->record(
            'Login / API rate limiters',
            $missing === [] ? 'PASS' : 'FAIL',
            $missing === [] ? 'All limiters configured' : 'Missing: '.implode(', ', $missing),
        );
    }

    private function checkAuthRouteThrottling(): void
    {
        $routes = collect(Route::getRoutes())->filter(function ($route): bool {
            $uri = $route->uri();

            return str_contains($uri, 'login')
                || str_contains($uri, 'register')
                || str_contains($uri, 'forgot-password')
                || str_contains($uri, 'reset-password');
        });

        $unthrottled = $routes
            ->filter(fn ($route) => in_array($route->methods()[0] ?? '', ['POST', 'PUT', 'PATCH'], true))
            ->filter(fn ($route) => ! collect($route->gatherMiddleware())->contains(
                fn ($middleware) => str_starts_with($middleware, 'throttle:'),
            ))
            ->map(fn ($route) => ($route->methods()[0] ?? '').' '.$route->uri())
            ->values()
            ->all();

        $this->record(
            'Throttle on auth routes',
            $unthrottled === [] ? 'PASS' : 'FAIL',
            $unthrottled === [] ? 'Login, register, and reset routes are throttled' : implode(', ', $unthrottled),
        );
    }

    private function checkPasswordHashing(): void
    {
        $userModel = \App\Modules\User\Models\User::class;
        $casts = (new $userModel)->getCasts();

        $this->record(
            'Password hashing (bcrypt)',
            ($casts['password'] ?? null) === 'hashed' ? 'PASS' : 'FAIL',
            ($casts['password'] ?? null) === 'hashed'
                ? 'User model uses hashed password cast'
                : 'User password cast is not "hashed"',
        );
    }

    private function checkDebugMode(): void
    {
        $isProduction = app()->environment('production');
        $debug = (bool) config('app.debug');

        if ($isProduction && $debug) {
            $this->record('Debug mode off in production', 'FAIL', 'APP_DEBUG=true while APP_ENV=production');

            return;
        }

        if ($debug && ! $isProduction) {
            $this->record('Debug mode off in production', 'WARN', 'APP_DEBUG=true (OK for local, disable in production)');

            return;
        }

        $this->record('Debug mode off in production', 'PASS', 'APP_DEBUG is disabled or environment is not production');
    }

    private function checkAppKey(): void
    {
        $key = (string) config('app.key');

        $this->record(
            'Application encryption key',
            $key !== '' ? 'PASS' : 'FAIL',
            $key !== '' ? 'APP_KEY is set' : 'APP_KEY is missing — run php artisan key:generate',
        );
    }

    private function checkSensitiveFileProtection(): void
    {
        $required = [
            base_path('.htaccess'),
            public_path('.htaccess'),
            storage_path('.htaccess'),
            database_path('.htaccess'),
            base_path('bootstrap/cache/.htaccess'),
        ];

        $missing = array_filter($required, fn (string $path) => ! File::exists($path));

        $this->record(
            'Hide .env / DB / storage files',
            $missing === [] ? 'PASS' : 'FAIL',
            $missing === [] ? '.htaccess protection files present' : 'Missing: '.implode(', ', $missing),
        );
    }

    private function checkSecurityMiddleware(): void
    {
        $router = app('router');
        $webStack = $router->getMiddlewareGroups()['web'] ?? [];
        $apiStack = $router->getMiddlewareGroups()['api'] ?? [];

        $hasForceHttps = in_array(\App\Core\Http\Middleware\ForceHttps::class, $webStack, true)
            && in_array(\App\Core\Http\Middleware\ForceHttps::class, $apiStack, true);
        $hasHeaders = in_array(\App\Core\Http\Middleware\SecurityHeaders::class, $webStack, true)
            && in_array(\App\Core\Http\Middleware\SecurityHeaders::class, $apiStack, true);

        $forceHttpsConfig = (bool) config('structer.security.force_https');

        $this->record(
            'HTTPS + security headers middleware',
            ($hasForceHttps && $hasHeaders) ? 'PASS' : 'FAIL',
            ($hasForceHttps && $hasHeaders)
                ? 'ForceHttps and SecurityHeaders registered'.($forceHttpsConfig ? ' (HTTPS enforced)' : ' (HTTPS off in local)')
                : 'Security middleware missing from web/api stack',
        );
    }

    private function checkFormRequestValidation(): void
    {
        $controllers = File::allFiles(app_path('Modules'));
        $missing = [];

        foreach ($controllers as $file) {
            if (! str_ends_with($file->getFilename(), 'Controller.php')) {
                continue;
            }

            $contents = File::get($file->getPathname());

            if (! preg_match_all('/function\s+\w+\(([^)]*Request\s+\$\w+[^)]*)\)/', $contents, $matches)) {
                continue;
            }

            foreach ($matches[1] as $parameter) {
                if (str_contains($parameter, 'Request $request') && ! str_contains($parameter, 'FormRequest')) {
                    $missing[] = $file->getFilename().' uses raw Request';
                }
            }
        }

        $this->record(
            'Input validation (FormRequest)',
            $missing === [] ? 'PASS' : 'WARN',
            $missing === [] ? 'Controllers use FormRequest classes' : implode('; ', array_unique($missing)),
        );
    }

    private function checkBladeEscaping(): void
    {
        $unsafe = [];

        foreach (File::allFiles(app_path('Modules')) as $file) {
            if (! str_contains($file->getPathname(), 'views') || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = File::get($file->getPathname());

            if (preg_match('/\{!!\s*.+\s*!!\}/', $contents)) {
                $unsafe[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }

        foreach (File::allFiles(resource_path('views')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = File::get($file->getPathname());

            if (preg_match('/\{!!\s*.+\s*!!\}/', $contents)) {
                $unsafe[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }

        $this->record(
            'Blade escaped output {{ }}',
            $unsafe === [] ? 'PASS' : 'WARN',
            $unsafe === [] ? 'No {!! !!} unescaped output in app views' : 'Unescaped: '.implode(', ', $unsafe),
        );
    }

    private function checkEloquentOnly(): void
    {
        $rawDb = $this->scanAppPhpForPatterns([
            '/\bDB::(select|insert|update|delete|statement|raw|unprepared)\s*\(/',
        ]);

        $this->record(
            'Eloquent ORM (no raw DB queries)',
            $rawDb === [] ? 'PASS' : 'WARN',
            $rawDb === [] ? 'Repositories use Eloquent only' : 'Raw DB usage: '.implode(', ', $rawDb),
        );
    }

    private function checkSqlInjectionPatterns(): void
    {
        $patterns = [
            '/\b(whereRaw|selectRaw|havingRaw|orderByRaw|groupByRaw)\s*\(/',
            '/\bDB::raw\s*\(/',
        ];

        $matches = $this->scanAppPhpForPatterns($patterns);

        $this->record(
            'SQL injection protection (parameter binding)',
            $matches === [] ? 'PASS' : 'FAIL',
            $matches === [] ? 'No raw SQL fragments in app code' : 'Found: '.implode(', ', $matches),
        );
    }

    private function checkUsersApiProtected(): void
    {
        $usersRoutes = collect(Route::getRoutes())->filter(
            fn ($route) => str_contains($route->uri(), 'api/') && str_contains($route->uri(), 'users'),
        );

        $unprotected = $usersRoutes
            ->filter(fn ($route) => ! in_array('auth:sanctum', $route->gatherMiddleware(), true))
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->record(
            'Users API requires authentication',
            $unprotected === [] && $usersRoutes->isNotEmpty() ? 'PASS' : ($usersRoutes->isEmpty() ? 'WARN' : 'FAIL'),
            $unprotected === []
                ? 'All /api/*/users routes use auth:sanctum'
                : 'Unprotected: '.implode(', ', $unprotected),
        );
    }

    private function checkEnvGitignored(): void
    {
        $gitignore = File::get(base_path('.gitignore'));

        $this->record(
            '.env excluded from git',
            str_contains($gitignore, '.env') ? 'PASS' : 'FAIL',
            str_contains($gitignore, '.env') ? '.env is in .gitignore' : '.env is NOT gitignored',
        );
    }

    private function runComposerAudit(): void
    {
        $process = new Process(['composer', 'audit', '--format=plain'], base_path());
        $process->run();

        $this->record(
            'Composer dependency audit',
            $process->isSuccessful() ? 'PASS' : 'FAIL',
            $process->isSuccessful() ? 'No known vulnerable packages' : trim($process->getErrorOutput() ?: $process->getOutput()),
        );
    }

    /**
     * @param  array<int, string>  $patterns
     * @return array<int, string>
     */
    private function scanAppPhpForPatterns(array $patterns): array
    {
        $found = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = File::get($file->getPathname());
            $relative = str_replace(base_path().'/', '', $file->getPathname());

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents)) {
                    $found[] = $relative;
                    break;
                }
            }
        }

        return array_values(array_unique($found));
    }

    private function record(string $check, string $status, string $detail): void
    {
        $this->results[] = compact('check', 'status', 'detail');
    }

    private function renderTable(): void
    {
        $this->table(
            ['Check', 'Status', 'Detail'],
            collect($this->results)->map(fn (array $row) => [
                $row['check'],
                match ($row['status']) {
                    'PASS' => '<fg=green>PASS</>',
                    'WARN' => '<fg=yellow>WARN</>',
                    default => '<fg=red>FAIL</>',
                },
                $row['detail'],
            ])->all(),
        );
    }
}
