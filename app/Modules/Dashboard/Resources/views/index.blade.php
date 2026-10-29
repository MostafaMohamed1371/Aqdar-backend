@extends('dashboard::layouts.app')

@section('title', __('aqdar.executive_dashboard'))
@section('screen', 'ADM-01')

@section('content')
    @php
        $chartRows = $charts['months'] ?? [];
        $chartMonthCount = (int) ($chartMonths ?? 6);
        $chartMax = max(array_merge(
            array_column($chartRows, 'approved'),
            array_column($chartRows, 'in_review'),
            array_column($chartRows, 'rejected'),
            [0],
        )) * 1.15;
        $chartMax = $chartMax > 0 ? $chartMax : 1;
        $chartW = 560;
        $chartH = 220;
        $chartPad = 30;
        $chartN = max(count($chartRows), 1);
        $chartM = 3;
        $chartGw = ($chartW - $chartPad - 10) / $chartN;
        $chartBw = min(18, ($chartGw - 12) / $chartM);
        $donut = $charts['donut'] ?? [];
        $donutTotal = max(array_sum(array_column($donut, 'count')), 1);
        $donutColors = ['approved' => '#087e82', 'in_review' => '#c5985f', 'rejected' => '#c0392b', 'hold' => '#9fb0c2'];
        $donutOff = 25;
        $needs = [
            ['listings_in_review', 'building', 'aqdar.needs.listings_in_review', 'approve_listing', 'dashboard.listings.index', ['status' => 'in_review']],
            ['documents_pending', 'doc', 'aqdar.needs.documents_pending', 'open_private_documents', 'dashboard.documents.index', []],
            ['new_buyer_requests', 'inbox', 'aqdar.needs.new_buyer_requests', 'manage_crm', 'dashboard.requests.index', ['status' => 'new']],
            ['awaiting_developer', 'swap', 'aqdar.needs.awaiting_developer', 'manage_crm', 'dashboard.cases.index', ['stage' => 'developer_approval']],
            ['unassigned_leads', 'users', 'aqdar.needs.unassigned_leads', 'manage_crm', 'dashboard.leads.index', ['assigned_to' => 'unassigned']],
            ['overdue_followups', 'clock', 'aqdar.needs.overdue_followups', 'manage_crm', 'dashboard.leads.index', ['status' => 'overdue']],
            ['contact_flagged', 'warn', 'aqdar.needs.contact_flagged', 'approve_listing', 'dashboard.listings.index', ['alerts' => '1']],
        ];
        $funnelColors = ['new' => 'var(--teal)', 'contacted' => 'var(--teal)', 'qualified' => '#5fb0b3', 'visit_scheduled' => '#c5985f', 'converted' => '#14283f'];
        $alertBad = ['unassigned_leads', 'overdue_followups'];
        $alertWarn = ['contact_flagged'];
        $preset = $preset ?? 'year';
        $deltas = $deltas ?? [];
        $presetQuery = fn (string $value) => route('dashboard', array_filter([
            'preset' => $value,
            'chart_months' => $chartMonthCount !== 6 ? $chartMonthCount : null,
        ]));
    @endphp

    <div class="ptitle">
        <h2><x-bilingual key="aqdar.executive_dashboard" /></h2>
        <div class="acts">
            <form method="GET" action="{{ route('dashboard') }}" class="filters" style="margin:0">
                <span class="btn ghost en">@include('dashboard::partials.icon', ['name' => 'calendar'])</span>
                <input type="date" name="from" value="{{ $from }}" class="btn ghost en">
                <input type="date" name="to" value="{{ $to }}" class="btn ghost en">
                <input type="hidden" name="chart_months" value="{{ $chartMonthCount }}">
                <button type="submit" class="btn ghost">{{ __('aqdar.reports.apply') }}</button>
            </form>
            <select class="btn ghost" onchange="window.location=this.value" aria-label="{{ __('aqdar.presets.label') }}">
                <option value="{{ $presetQuery('year') }}" @selected($preset === 'year')>{{ __('aqdar.presets.year') }}</option>
                <option value="{{ $presetQuery('month') }}" @selected($preset === 'month')>{{ __('aqdar.presets.month') }}</option>
                <option value="{{ $presetQuery('30d') }}" @selected($preset === '30d')>{{ __('aqdar.presets.days_30') }}</option>
                <option value="{{ $presetQuery('6m') }}" @selected($preset === '6m')>{{ __('aqdar.presets.months_6') }}</option>
            </select>
        </div>
    </div>

    <div class="kpis k5">
        @foreach ([
            ['building', '', 'total_listings', 'aqdar.total_listings'],
            ['clock', 'warn', 'listings_in_review', 'aqdar.listings_in_review'],
            ['shield', 'ok', 'listings_approved', 'aqdar.listings_approved'],
            ['x', 'bad', 'listings_rejected', 'aqdar.listings_rejected'],
            ['briefcase', 'info', 'approved_developers', 'aqdar.approved_developers'],
        ] as [$icon, $tone, $key, $label])
            <div class="kpi">
                <span class="ic {{ $tone }}">@include('dashboard::partials.icon', ['name' => $icon])</span>
                <div style="flex:1">
                    <span>{{ __($label) }}</span>
                    <b>{{ number_format((float) ($stats[$key] ?? 0)) }}</b>
                    @include('dashboard::partials.kpi-delta', ['key' => $key, 'deltas' => $deltas])
                </div>
            </div>
        @endforeach
    </div>

    <div class="kpis k5">
        @foreach ([
            ['chat', '', 'inquiries_in_range', ($preset ?? '') === 'month' ? 'aqdar.inquiries_this_month' : 'aqdar.inquiries_in_range'],
            ['inbox', 'gold', 'custom_buy_requests', 'aqdar.custom_buy_requests'],
            ['clock', 'bad', 'overdue_followups', 'aqdar.overdue_followups'],
            ['swap', 'info', 'active_transfers', 'aqdar.active_transfers'],
            ['cash', 'ok', 'recorded_commissions', 'aqdar.recorded_commissions'],
        ] as [$icon, $tone, $key, $label])
            <div class="kpi">
                <span class="ic {{ $tone }}">@include('dashboard::partials.icon', ['name' => $icon])</span>
                <div style="flex:1">
                    <span>{{ __($label) }}</span>
                    <b>{{ $key === 'recorded_commissions' ? \App\Modules\Dashboard\Services\DashboardService::compactEgp((float) ($stats[$key] ?? 0)) : number_format((float) ($stats[$key] ?? 0)) }}</b>
                    @include('dashboard::partials.kpi-delta', ['key' => $key, 'deltas' => $deltas])
                </div>
            </div>
        @endforeach
    </div>

    <p class="tiny" data-ops-health>
        {{ __('aqdar.ops.failed_jobs') }}: <b class="en">{{ number_format((float) ($stats['failed_jobs'] ?? 0)) }}</b>
        · {{ __('aqdar.ops.failed_pushes') }}: <b class="en">{{ number_format((float) ($stats['failed_pushes'] ?? 0)) }}</b>
    </p>

    <div class="cols3 cols3-dash">
        <div class="card" data-chart="listing-performance">
            <div class="card-h">
                <b>{{ __('aqdar.charts.listing_performance') }}</b>
                <select class="btn ghost sm" onchange="window.location=this.value">
                    @foreach ([3, 6, 12] as $n)
                        <option value="{{ route('dashboard', array_filter(['from' => $from, 'to' => $to, 'preset' => $preset !== 'custom' ? $preset : null, 'chart_months' => $n])) }}" @selected($chartMonthCount === $n)>{{ __('aqdar.presets.last_n_months', ['n' => $n]) }}</option>
                    @endforeach
                </select>
                <div class="legend">
                    <span><i style="background:#087e82"></i>{{ __('aqdar.listings_approved') }}</span>
                    <span><i style="background:#c5985f"></i>{{ __('aqdar.listings_in_review') }}</span>
                    <span><i style="background:#c0392b"></i>{{ __('aqdar.listings_rejected') }}</span>
                </div>
            </div>
            <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" width="100%" style="direction:ltr;display:block">
                @for ($grid = 0; $grid <= 4; $grid++)
                    @php $yy = 14 + ($chartH - 40) * $grid / 4; @endphp
                    <line x1="{{ $chartPad }}" x2="{{ $chartW - 10 }}" y1="{{ $yy }}" y2="{{ $yy }}" stroke="#eef0ee"/>
                @endfor
                @foreach ($chartRows as $i => $row)
                    @php
                        $gx = $chartPad + $chartGw * $i + ($chartGw - $chartBw * $chartM - 4 * ($chartM - 1)) / 2;
                        $hA = ($chartH - 40) * ((float) $row['approved']) / $chartMax;
                        $hR = ($chartH - 40) * ((float) $row['in_review']) / $chartMax;
                        $hX = ($chartH - 40) * ((float) $row['rejected']) / $chartMax;
                    @endphp
                    <rect x="{{ $gx }}" y="{{ $chartH - 26 - $hA }}" width="{{ $chartBw }}" height="{{ $hA }}" rx="5" fill="#087e82"/>
                    <rect x="{{ $gx + $chartBw + 4 }}" y="{{ $chartH - 26 - $hR }}" width="{{ $chartBw }}" height="{{ $hR }}" rx="5" fill="#c5985f"/>
                    <rect x="{{ $gx + ($chartBw + 4) * 2 }}" y="{{ $chartH - 26 - $hX }}" width="{{ $chartBw }}" height="{{ $hX }}" rx="5" fill="#c0392b"/>
                    <text x="{{ $chartPad + $chartGw * $i + $chartGw / 2 }}" y="{{ $chartH - 8 }}" text-anchor="middle" font-size="10.5" fill="#6b7280">{{ $row['label'] }}</text>
                @endforeach
            </svg>
        </div>
        <div class="card" data-chart="listing-status">
            <div class="card-h"><b>{{ __('aqdar.charts.listing_status') }}</b></div>
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;justify-content:center">
                <svg viewBox="0 0 42 42" width="140" height="140" style="flex-shrink:0">
                    @foreach ($donut as $seg)
                        @php $pct = round(($seg['count'] / $donutTotal) * 100, 1); @endphp
                        <circle r="15.915" cx="21" cy="21" fill="none" stroke="{{ $donutColors[$seg['key']] ?? '#9fb0c2' }}" stroke-width="6" stroke-dasharray="{{ $pct }} {{ 100 - $pct }}" stroke-dashoffset="{{ $donutOff }}"/>
                        @php $donutOff -= $pct; @endphp
                    @endforeach
                    <text x="21" y="21.5" text-anchor="middle" font-size="6.2" font-weight="800" fill="#14283f">{{ number_format((float) ($stats['total_listings'] ?? 0)) }}</text>
                    <text x="21" y="27" text-anchor="middle" font-size="3.2" fill="#6b7280">{{ __('aqdar.total_listings') }}</text>
                </svg>
                <div style="flex:1;min-width:130px">
                    @foreach ($donut as $seg)
                        <div class="dl" style="white-space:nowrap">
                            <span><i style="display:inline-block;width:10px;height:10px;border-radius:3px;background:{{ $donutColors[$seg['key']] ?? '#9fb0c2' }};margin-inline-end:6px"></i>{{ __('aqdar.charts.'.$seg['key']) }}</span>
                            <b class="en">{{ number_format($seg['count']) }}</b>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @include('dashboard::partials.egypt-map', ['charts' => $charts])
    </div>

    <div class="cols2e">
        <div class="card">
            <div class="card-h">
                <b>{{ __('aqdar.funnel.title') }}</b>
                <span class="tiny">{{ __('aqdar.funnel.hint') }}</span>
            </div>
            <div class="bars-h">
                @foreach ($funnel as $step)
                    <div class="bh">
                        <span>{{ __('aqdar.funnel.'.$step['key']) }}</span>
                        <div class="tr"><i style="width:{{ $step['pct'] }}%;background:{{ $funnelColors[$step['key']] ?? 'var(--teal)' }}"></i></div>
                        <b>{{ number_format($step['count']) }}</b>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.needs.title') }}</b></div>
            @foreach ($needs as [$key, $icon, $label, $can, $route, $query])
                @php $count = (int) ($needsAction[$key] ?? 0); @endphp
                <div class="dl">
                    <span>@include('dashboard::partials.icon', ['name' => $icon, 'size' => 'xs']) {{ __($label) }}</span>
                    @can($can)
                        <a href="{{ route($route, $query) }}" class="en" @style([
                            'color:var(--bad)' => in_array($key, $alertBad, true) && $count > 0,
                            'color:var(--warn)' => in_array($key, $alertWarn, true) && $count > 0,
                        ])><b>{{ number_format($count) }}</b></a>
                    @else
                        <b class="en">{{ number_format($count) }}</b>
                    @endcan
                </div>
            @endforeach
        </div>
    </div>
@endsection
