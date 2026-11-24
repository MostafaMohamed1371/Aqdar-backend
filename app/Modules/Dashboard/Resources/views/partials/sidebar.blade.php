@php
    $menu = [
        'home' => [
            ['id' => 'ADM-01', 'icon' => 'home', 'key' => 'aqdar.menu.adm_01', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard') && ! request()->routeIs('dashboard.*')],
            ['id' => 'GUIDE', 'icon' => 'doc', 'key' => 'aqdar.menu.guide', 'route' => 'dashboard.guide', 'active' => request()->routeIs('dashboard.guide') || request()->routeIs('dashboard.guide.*')],
        ],
        'operations' => [
            ['id' => 'ADM-02', 'icon' => 'building', 'key' => 'aqdar.menu.adm_02', 'route' => 'dashboard.listings.index', 'active' => request()->routeIs('dashboard.listings.*'), 'can' => 'approve_listing'],
            ['id' => 'ADM-03', 'icon' => 'doc', 'key' => 'aqdar.menu.adm_03', 'route' => 'dashboard.documents.index', 'active' => request()->routeIs('dashboard.documents.*'), 'can' => 'open_private_documents'],
            ['id' => 'ADM-04', 'icon' => 'users', 'key' => 'aqdar.menu.adm_04', 'route' => 'dashboard.users.index', 'active' => request()->routeIs('dashboard.users.*'), 'can' => 'view_users'],
            ['id' => 'ADM-05', 'icon' => 'db', 'key' => 'aqdar.menu.adm_05', 'route' => 'dashboard.developers.index', 'active' => request()->routeIs('dashboard.developers.*') || request()->routeIs('dashboard.projects.*'), 'can' => 'manage_catalog'],
        ],
        'admin' => [
            ['id' => 'ADM-06', 'icon' => 'key', 'key' => 'aqdar.menu.adm_06', 'route' => 'dashboard.staff.index', 'active' => request()->routeIs('dashboard.staff.*'), 'can' => 'manage_staff'],
            ['id' => 'ADM-07', 'icon' => 'target', 'key' => 'aqdar.menu.adm_07', 'route' => 'dashboard.leads.index', 'active' => request()->routeIs('dashboard.leads.*') || request()->routeIs('dashboard.support.*'), 'can' => 'manage_crm'],
            ['id' => 'ADM-08', 'icon' => 'inbox', 'key' => 'aqdar.menu.adm_08', 'route' => 'dashboard.requests.index', 'active' => request()->routeIs('dashboard.requests.*'), 'can' => 'manage_crm'],
            ['id' => 'ADM-09', 'icon' => 'swap', 'key' => 'aqdar.menu.adm_09', 'route' => 'dashboard.cases.index', 'active' => request()->routeIs('dashboard.cases.*'), 'can' => ['manage_crm', 'advance_finance_stages']],
        ],
        'finance' => [
            ['id' => 'ADM-10', 'icon' => 'percent', 'key' => 'aqdar.menu.adm_10', 'route' => 'dashboard.commissions.index', 'active' => request()->routeIs('dashboard.commissions.*'), 'can' => ['edit_commissions', 'approve_commissions', 'view_commissions']],
            ['id' => 'ADM-11', 'icon' => 'megaphone', 'key' => 'aqdar.menu.adm_11', 'route' => 'dashboard.content.index', 'active' => request()->routeIs('dashboard.content.*'), 'can' => ['manage_catalog', 'approve_broadcasts']],
            ['id' => 'ADM-12', 'icon' => 'chart', 'key' => 'aqdar.menu.adm_12', 'route' => 'dashboard.reports.index', 'active' => request()->routeIs('dashboard.reports.*'), 'can' => 'export_csv'],
        ],
        'system' => [
            ['id' => 'ADM-13', 'icon' => 'settings', 'key' => 'aqdar.menu.adm_13', 'route' => 'dashboard.settings.index', 'active' => request()->routeIs('dashboard.settings.*'), 'can' => 'manage_catalog'],
            ['id' => 'ADM-14', 'icon' => 'list', 'key' => 'aqdar.menu.adm_14', 'route' => 'dashboard.audit.index', 'active' => request()->routeIs('dashboard.audit.*'), 'can' => 'manage_staff'],
            ['id' => 'ADM-15', 'icon' => 'globe', 'key' => 'aqdar.menu.adm_15', 'route' => 'dashboard.categories.index', 'active' => request()->routeIs('dashboard.categories.*') || request()->routeIs('dashboard.locations.*') || request()->routeIs('dashboard.unit-types.*') || request()->routeIs('dashboard.amenities.*') || request()->routeIs('dashboard.tags.*'), 'can' => 'manage_catalog'],
        ],
    ];
@endphp

<aside class="dside" data-sidebar>
    <div class="dside-top">
        <a href="{{ route('dashboard') }}" class="brand-link">
            @include('dashboard::partials.brand', ['variant' => 'light'])
        </a>
        <button type="button" class="icon-btn" data-sidebar-close aria-label="{{ __('structer.close_menu') }}">
            @include('dashboard::partials.icon', ['name' => 'x'])
        </button>
    </div>

    @foreach ($menu as $group => $items)
        <div class="grp"><x-bilingual :key="'aqdar.group.'.$group" /></div>
        @foreach ($items as $item)
            @php
                $perms = $item['can'] ?? null;
                $canSee = empty($perms) || collect(is_array($perms) ? $perms : [$perms])
                    ->contains(fn ($permission) => auth()->user()?->can($permission));
            @endphp
            @if (! empty($item['route']) && $canSee)
                <a href="{{ route($item['route']) }}" @class(['on' => $item['active'] ?? false])>
                    @include('dashboard::partials.icon', ['name' => $item['icon']])
                    <span><x-bilingual :key="$item['key']" /></span>
                    @if (! empty($queueBadges[$item['id']]))
                        <span class="cnt">{{ number_format($queueBadges[$item['id']]) }}</span>
                    @endif
                </a>
            @else
                <span class="nav-item soon" title="{{ __('aqdar.coming_soon') }}">
                    @include('dashboard::partials.icon', ['name' => $item['icon']])
                    <span><x-bilingual :key="$item['key']" /></span>
                </span>
            @endif
        @endforeach
    @endforeach
</aside>
