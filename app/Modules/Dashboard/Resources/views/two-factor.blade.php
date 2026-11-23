<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>{{ __('aqdar.auth.staff_two_factor_title') }} — {{ config('app.name') }}</title>
    @include('dashboard::partials.favicon')
    @include('dashboard::partials.theme-script')
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="aqdar-login" data-screen="staff-two-factor">
    <div class="login-top">
        @include('dashboard::partials.theme-toggle')
        @include('dashboard::partials.locale-switcher')
    </div>

    <div class="login-card card">
        @include('dashboard::partials.brand', ['variant' => ''])
        <h1>{{ __('aqdar.auth.staff_two_factor_title') }}</h1>
        <p class="sub">{{ __('aqdar.auth.staff_two_factor_hint') }}</p>

        @include('dashboard::partials.flash')

        <form method="POST" action="{{ route('dashboard.two-factor.verify') }}" novalidate>
            @csrf

            <x-form-input
                name="code"
                :label="__('aqdar.auth.staff_two_factor_code')"
                type="text"
                placeholder="000000"
                required
                autofocus
            />

            <button type="submit" class="btn">{{ __('aqdar.auth.staff_two_factor_submit') }}</button>
        </form>
    </div>
</body>
</html>
