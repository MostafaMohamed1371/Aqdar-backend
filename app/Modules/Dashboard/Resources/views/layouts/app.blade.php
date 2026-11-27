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
    data-label-feed-listing="{{ __('aqdar.live_toast.feed_listing') }}"
    data-label-feed-media="{{ __('aqdar.live_toast.feed_media') }}"
    data-label-feed-document="{{ __('aqdar.live_toast.feed_document') }}"
    data-label-feed-request="{{ __('aqdar.live_toast.feed_request') }}"
    data-label-feed-lead="{{ __('aqdar.live_toast.feed_lead') }}"
    data-label-feed-case="{{ __('aqdar.live_toast.feed_case') }}"
>
    <div class="desk-toast-stack" data-desk-toasts aria-live="polite"></div>
    <aside class="desk-live-feed" data-desk-live-feed hidden>
        <div class="desk-live-feed__head">
            <b>{{ __('aqdar.live_toast.feed_title') }}</b>
            <button type="button" class="btn ghost sm" data-desk-live-feed-close aria-label="{{ __('structer.close_menu') }}">×</button>
        </div>
        <div class="desk-live-feed__empty tiny" data-desk-live-feed-empty>{{ __('aqdar.live_toast.feed_empty') }}</div>
        <div class="desk-live-feed__list" data-desk-live-feed-list></div>
    </aside>
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
