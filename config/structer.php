<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Route Prefix
    |--------------------------------------------------------------------------
    |
    | All module API routes are registered under /api/{prefix}/...
    |
    */

    'api_prefix' => env('STRUCTER_API_PREFIX', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Disabled Modules
    |--------------------------------------------------------------------------
    |
    | List module folder names to skip during auto-discovery.
    |
    */

    'disabled_modules' => [],

    /*
    |--------------------------------------------------------------------------
    | Modules Path
    |--------------------------------------------------------------------------
    */

    'modules_path' => app_path('Modules'),

    /*
    |--------------------------------------------------------------------------
    | Frontend URL (password reset links)
    |--------------------------------------------------------------------------
    */

    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),

    'password_reset_path' => env('PASSWORD_RESET_PATH', '/reset-password'),

    /*
    |--------------------------------------------------------------------------
    | Available Locales (laravel-lang)
    |--------------------------------------------------------------------------
    */

    'available_locales' => ['ar', 'en'],

    'locale_labels' => [
        'ar' => 'العربية',
        'en' => 'English',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    'security' => [
        'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV') === 'production'),
        'hsts' => (bool) env('SECURITY_HSTS', env('APP_ENV') === 'production'),
        'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_minutes' => (int) env('LOGIN_DECAY_MINUTES', 1),
        'api_auth_max_attempts' => (int) env('API_AUTH_MAX_ATTEMPTS', 10),
        'api_auth_decay_minutes' => (int) env('API_AUTH_DECAY_MINUTES', 1),
        'password_reset_max_attempts' => (int) env('PASSWORD_RESET_MAX_ATTEMPTS', 3),
        'password_reset_decay_minutes' => (int) env('PASSWORD_RESET_DECAY_MINUTES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scaling (100k+ users)
    |--------------------------------------------------------------------------
    |
    | Redis: shared sessions, cache, and queues across stateless app servers.
    | Horizon: background workers (default > emails). AQDAR has no payment gateway.
    | See SCALING.md for the full architecture checklist.
    |
    */

    'scaling' => [
        'cache_ttl_seconds' => (int) env('STRUCTER_CACHE_TTL', 3600),
        'reservation_minutes' => (int) env('STRUCTER_RESERVATION_MINUTES', 10),
        'reservation_prefix' => env('STRUCTER_RESERVATION_PREFIX', 'reservation:'),
        'lock_prefix' => env('STRUCTER_LOCK_PREFIX', 'lock:'),
        'lock_seconds' => (int) env('STRUCTER_LOCK_SECONDS', 10),
        'horizon_allowed_emails' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('HORIZON_ALLOWED_EMAILS', '')),
        ))),
    ],

    'queues' => [
        'payments' => env('QUEUE_PAYMENTS', 'payments'),
        'emails' => env('QUEUE_EMAILS', 'emails'),
        'default' => env('QUEUE_DEFAULT', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | JMeter load testing
    |--------------------------------------------------------------------------
    */

    'jmeter' => [
        'load_test' => (bool) env('STRUCTER_JMETER_LOAD_TEST', false),
    ],

];
