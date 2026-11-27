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
    @include('dashboard::partials.theme-toggle')
    <div class="locale-mini">
        @include('dashboard::partials.locale-switcher')
    </div>
    <a
        href="{{ route('dashboard.inbox') }}"
        class="icon-btn"
        data-inbox-bell
        data-unread="{{ $inboxUnread }}"
        aria-label="{{ __('aqdar.notifications.inbox') }}"
    >
        @include('dashboard::partials.icon', ['name' => 'bell'])
        <span class="dot" data-inbox-dot @if ($inboxUnread < 1) hidden @endif></span>
    </a>
    @can('manage_catalog')
        <a href="{{ route('dashboard.settings.index') }}" class="icon-btn" aria-label="{{ __('structer.settings') }}">
            @include('dashboard::partials.icon', ['name' => 'settings'])
        </a>
    @endcan
    <a href="{{ route('dashboard.profile.show') }}" class="who{{ request()->routeIs('dashboard.profile.*') ? ' on' : '' }}" aria-label="{{ __('aqdar.profile.open') }}">
        @include('dashboard::partials.staff-avatar')
        <div>
            <b>{{ auth()->user()->name }}</b>
            <span>{{ auth()->user()->roleName() }}</span>
        </div>
    </a>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn ghost sm">{{ __('structer.sign_out') }}</button>
    </form>
</div>
