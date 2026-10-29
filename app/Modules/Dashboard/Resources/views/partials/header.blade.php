@php
    $headerSearch = match (true) {
        request()->routeIs('dashboard.listings.*') => route('dashboard.listings.index'),
        request()->routeIs('dashboard.documents.*') => route('dashboard.documents.index'),
        request()->routeIs('dashboard.users.*') => route('dashboard.users.index'),
        request()->routeIs('dashboard.staff.*') => route('dashboard.staff.index'),
        request()->routeIs('dashboard.leads.*', 'dashboard.support.*') => route('dashboard.leads.index'),
        request()->routeIs('dashboard.requests.*') => route('dashboard.requests.index'),
        request()->routeIs('dashboard.cases.*') => route('dashboard.cases.index'),
        request()->routeIs('dashboard.commissions.*') => route('dashboard.commissions.index'),
        request()->routeIs('dashboard.audit.*') => route('dashboard.audit.index'),
        auth()->user()?->can('approve_listing') => route('dashboard.listings.index'),
        auth()->user()?->can('view_users') => route('dashboard.users.index'),
        default => route('dashboard'),
    };
@endphp
<div class="dhead">
    <button type="button" class="icon-btn" data-sidebar-open aria-label="{{ __('structer.open_menu') }}" aria-expanded="false">
        @include('dashboard::partials.icon', ['name' => 'menu'])
    </button>
    <form method="GET" action="{{ $headerSearch }}" class="q" role="search">
        @include('dashboard::partials.icon', ['name' => 'search'])
        <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('aqdar.search_placeholder') }}" aria-label="{{ __('aqdar.search_placeholder') }}">
    </form>
    <span class="grow"></span>
    <div class="locale-mini">
        @include('dashboard::partials.locale-switcher')
    </div>
    <a href="{{ route('dashboard.inbox') }}" class="icon-btn" aria-label="{{ __('aqdar.notifications.inbox') }}">
        @include('dashboard::partials.icon', ['name' => 'bell'])
        @if (app(\App\Modules\Notification\Services\NotificationService::class)->unreadCount(auth()->user()) > 0)
            <span class="dot"></span>
        @endif
    </a>
    @can('manage_catalog')
        <a href="{{ route('dashboard.settings.index') }}" class="icon-btn" aria-label="{{ __('structer.settings') }}">
            @include('dashboard::partials.icon', ['name' => 'settings'])
        </a>
    @endcan
    <div class="who">
        <span class="av s c3">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
        <div>
            <b>{{ auth()->user()->name }}</b>
            <span>{{ auth()->user()->email }}</span>
        </div>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn ghost sm">{{ __('structer.sign_out') }}</button>
    </form>
</div>
