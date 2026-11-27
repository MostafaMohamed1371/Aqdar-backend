<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <meta name="broadcast-driver" content="{{ config('broadcasting.default') }}">
    <meta name="pusher-key" content="{{ config('broadcasting.connections.pusher.key') }}">
    <meta name="pusher-cluster" content="{{ config('broadcasting.connections.pusher.options.cluster', 'eu') }}">
    <title>@yield('title', __('structer.dashboard')) — {{ config('app.name') }}</title>
    @include('dashboard::partials.favicon')
    @include('dashboard::partials.theme-script')
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
@php($inboxUnread = app(\App\Modules\Notification\Services\NotificationService::class)->unreadCount(auth()->user()))
<body
    class="aqdar-desk"
    data-staff-id="{{ auth()->id() }}"
    data-can-crm="{{ auth()->user()?->can('manage_crm') ? '1' : '0' }}"
    data-unread="{{ $inboxUnread }}"
    data-desk-live="{{ route('dashboard.live', absolute: false) }}"
    data-desk-live-ping="{{ route('dashboard.live.ping', absolute: false) }}"
    data-label-unread="{{ __('aqdar.notifications.unread') }}"
    data-label-view="{{ __('aqdar.listings.desk.view') }}"
    data-toast-waiting="{{ __('aqdar.live_toast.waiting') }}"
    data-toast-open="{{ __('aqdar.live_toast.open') }}"
    data-toast-listing="{{ __('aqdar.live_toast.listing_submitted') }}"
    data-toast-media="{{ __('aqdar.live_toast.media_uploaded') }}"
    data-toast-document="{{ __('aqdar.live_toast.document_uploaded') }}"
    data-toast-lead="{{ __('aqdar.live_toast.lead_created') }}"
    data-toast-request="{{ __('aqdar.live_toast.request_created') }}"
    data-toast-case="{{ __('aqdar.live_toast.case_opened') }}"
    data-label-poll-ok="{{ __('aqdar.live_toast.poll_ok') }}"
    data-label-poll-wait="{{ __('aqdar.live_toast.poll_wait') }}"
    data-label-poll-fail="{{ __('aqdar.live_toast.poll_fail') }}"
    data-label-pusher-on="{{ __('aqdar.live_toast.pusher_connected') }}"
    data-label-pusher-off="{{ __('aqdar.live_toast.pusher_offline') }}"
    data-label-test-live="{{ __('aqdar.live_toast.test_live') }}"
>
    <div class="desk-toast-stack" data-desk-toasts aria-live="polite"></div>
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
    @stack('scripts')
</body>
</html>
