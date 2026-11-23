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
<body
    class="aqdar-desk"
    data-staff-id="{{ auth()->id() }}"
    data-can-crm="{{ auth()->user()?->can('manage_crm') ? '1' : '0' }}"
    data-label-unread="{{ __('aqdar.notifications.unread') }}"
    data-label-view="{{ __('aqdar.listings.desk.view') }}"
>
    <div class="desk {{ app()->getLocale() === 'ar' ? 'is-rtl' : 'is-ltr' }}">
        @include('dashboard::partials.sidebar')
        <div class="dmain">
            @include('dashboard::partials.header')
            <main class="dbody" data-screen="@yield('screen', 'ADM-01')">
                @include('dashboard::partials.flash')
                @yield('content')
            </main>
        </div>
    </div>
    <div class="desk-backdrop" data-sidebar-backdrop hidden></div>
</body>
</html>
