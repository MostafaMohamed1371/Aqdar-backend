<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', __('structer.dashboard')) — {{ config('app.name') }}</title>
    @include('dashboard::partials.favicon')
    @include('dashboard::partials.theme-script')
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="aqdar-login">
    <div class="login-top">
        @include('dashboard::partials.theme-toggle')
        @include('dashboard::partials.locale-switcher')
    </div>

    <div class="login-card card">
        @include('dashboard::partials.brand', ['variant' => ''])

        @if (session('status'))
            <div class="alert" style="background:var(--ok-soft);border-color:#b7e0cc;color:var(--ok)">
                <b>{{ session('status') }}</b>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
