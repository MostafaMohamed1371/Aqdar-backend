<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>{{ __('structer.dashboard') }} — {{ config('app.name') }}</title>
    @include('dashboard::partials.favicon')
    @include('dashboard::partials.theme-script')
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="aqdar-login" data-screen="staff-login">
    <div class="login-top">
        @include('dashboard::partials.theme-toggle')
        @include('dashboard::partials.locale-switcher')
    </div>

    <div class="login-card card">
        @include('dashboard::partials.brand', ['variant' => ''])
        <h1>{{ config('app.name') }}</h1>
        <p class="sub">{{ __('structer.sign_in_to_dashboard') }}</p>

        @if (session('status'))
            <div class="alert" style="background:var(--ok-soft);border-color:#b7e0cc;color:var(--ok)">
                <b>{{ session('status') }}</b>
            </div>
        @endif

        @php
            $rememberedEmail = old('email');
            $rememberChecked = (bool) old('remember');
        @endphp

        <form method="POST" action="{{ route('dashboard.login') }}" novalidate>
            @csrf

            <x-form-input
                name="email"
                :label="__('structer.email')"
                type="email"
                :placeholder="__('structer.email_placeholder')"
                :value="$rememberedEmail"
                required
                autofocus
            />

            <x-password-input
                name="password"
                :placeholder="__('structer.password_mask_placeholder')"
                required
            >
                <x-slot:label>
                    <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                        <label for="password" class="text-sm font-medium" style="color:var(--ink)">
                            {{ __('structer.password') }} <span style="color:var(--bad)">*</span>
                        </label>
                        <a href="{{ route('password.request') }}" style="color:var(--teal);font-size:13px;font-weight:700">
                            {{ __('structer.forgot_password') }}
                        </a>
                    </div>
                </x-slot:label>
            </x-password-input>

            <label class="check">
                <input type="checkbox" name="remember" value="1" @checked($rememberChecked)>
                {{ __('structer.remember_me') }}
            </label>
            @error('remember')
                <p class="hint" style="color:var(--bad)">{{ $message }}</p>
            @enderror

            <button type="submit" class="btn">{{ __('structer.sign_in') }}</button>
        </form>
    </div>
</body>
</html>
