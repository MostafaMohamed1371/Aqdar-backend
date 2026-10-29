@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_12'))
@section('screen', 'ADM-12')

@section('content')
    @php
        $chartMonths = $charts ?? [];
        $chartMax = max(array_merge(
            array_column($chartMonths, 'listings'),
            array_column($chartMonths, 'requests'),
            array_column($chartMonths, 'users'),
            [0],
        )) * 1.15;
        $chartMax = $chartMax > 0 ? $chartMax : 1;
        $chartW = 560;
        $chartH = 220;
        $chartPad = 30;
        $chartN = max(count($chartMonths), 1);
        $chartM = 3;
        $chartGw = ($chartW - $chartPad - 10) / $chartN;
        $chartBw = min(18, ($chartGw - 12) / $chartM);
    @endphp

    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_12" /></h2>
    </div>

    <div class="kpis" style="grid-template-columns:repeat(3,1fr)">
        @foreach ([
            ['inbox', '', 'requests', 'aqdar.reports.kpi.requests'],
            ['building', 'gold', 'listings', 'aqdar.reports.kpi.listings'],
            ['users', 'info', 'users', 'aqdar.reports.kpi.users'],
        ] as [$icon, $tone, $key, $label])
            <div class="kpi">
                <span class="ic {{ $tone }}">@include('dashboard::partials.icon', ['name' => $icon])</span>
                <div style="flex:1">
                    <span>{{ __($label) }}</span>
                    <b>{{ number_format((float) ($kpis[$key] ?? 0)) }}</b>
                    @include('dashboard::partials.kpi-delta', ['key' => $key, 'deltas' => $deltas ?? []])
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('dashboard.reports.index') }}" class="filters">
        <select name="status">
            <option value="all" @selected($filters['status'] === 'all')>{{ __('aqdar.reports.status_all') }}</option>
            <option value="published" @selected($filters['status'] === 'published')>{{ __('aqdar.listings_published') }}</option>
            <option value="in_review" @selected($filters['status'] === 'in_review')>{{ __('aqdar.listings_in_review') }}</option>
            <option value="approved" @selected($filters['status'] === 'approved')>{{ __('aqdar.listings_approved') }}</option>
            <option value="new" @selected($filters['status'] === 'new')>{{ __('aqdar.funnel.new') }}</option>
        </select>
        <select name="source">
            <option value="all" @selected($filters['source'] === 'all')>{{ __('aqdar.reports.source_all') }}</option>
            <option value="listing_inquiry" @selected($filters['source'] === 'listing_inquiry')>{{ __('aqdar.reports.source.listing_inquiry') }}</option>
            <option value="custom_request" @selected($filters['source'] === 'custom_request')>{{ __('aqdar.reports.source.custom_request') }}</option>
        </select>
        <select name="owner">
            <option value="">{{ __('aqdar.crm.all_owners') }}</option>
            @foreach ($owners as $staff)
                <option value="{{ $staff->id }}" @selected((string) $filters['owner'] === (string) $staff->id)>{{ $staff->name }}</option>
            @endforeach
        </select>
        <select name="segment">
            <option value="all" @selected($filters['segment'] === 'all')>{{ __('aqdar.reports.segment_all') }}</option>
            <option value="distressed" @selected($filters['segment'] === 'distressed')>{{ __('aqdar.reports.segment.distressed') }}</option>
            <option value="non_distressed" @selected($filters['segment'] === 'non_distressed')>{{ __('aqdar.reports.segment.non_distressed') }}</option>
        </select>
        <select name="preset" onchange="this.form.from.value=''; this.form.to.value=''; this.form.submit()">
            <option value="month" @selected(($filters['preset'] ?? '') === 'month')>{{ __('aqdar.presets.month') }}</option>
            <option value="30d" @selected(($filters['preset'] ?? '') === '30d')>{{ __('aqdar.presets.days_30') }}</option>
            <option value="6m" @selected(($filters['preset'] ?? '') === '6m')>{{ __('aqdar.presets.months_6') }}</option>
            <option value="year" @selected(($filters['preset'] ?? '') === 'year')>{{ __('aqdar.presets.year') }}</option>
        </select>
        <input type="date" name="from" value="{{ $filters['from'] }}" class="btn ghost en">
        <input type="date" name="to" value="{{ $filters['to'] }}" class="btn ghost en">
        <button type="submit" class="btn ghost">{{ __('aqdar.reports.apply') }}</button>
    </form>

    <div class="kpis" style="grid-template-columns:repeat(4,1fr)">
        @foreach ([
            ['clock', '', number_format((float) ($rates['avg_listing_review_hours'] ?? 0), 1).' '. __('aqdar.reports.hours_short'), 'aqdar.reports.kpi.review_hours'],
            ['phone', 'gold', number_format((float) ($rates['first_response_hours'] ?? 0), 1).' '. __('aqdar.reports.hours_short'), 'aqdar.reports.kpi.first_response'],
            ['target', 'info', number_format((float) ($rates['qualified_rate'] ?? 0), 1).'%', 'aqdar.reports.kpi.qualified'],
            ['swap', 'ok', number_format((float) ($rates['converted_rate'] ?? 0), 1).'%', 'aqdar.reports.kpi.converted'],
        ] as [$icon, $tone, $value, $label])
            <div class="kpi">
                <span class="ic {{ $tone }}">@include('dashboard::partials.icon', ['name' => $icon])</span>
                <div>
                    <span>{{ __($label) }}</span>
                    <b class="en">{{ $value }}</b>
                </div>
            </div>
        @endforeach
    </div>

    <div class="cols2">
        <div class="card" data-chart="platform-overview">
            <div class="card-h">
                <b>{{ __('aqdar.reports.overview') }}</b>
                <div class="legend">
                    <span><i style="background:#087e82"></i>{{ __('aqdar.reports.kpi.listings') }}</span>
                    <span><i style="background:#c5985f"></i>{{ __('aqdar.reports.kpi.requests') }}</span>
                    <span><i style="background:#9fb0c2"></i>{{ __('aqdar.reports.kpi.users') }}</span>
                </div>
            </div>
            <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" width="100%" style="direction:ltr;display:block">
                @for ($grid = 0; $grid <= 4; $grid++)
                    @php $yy = 14 + ($chartH - 40) * $grid / 4; @endphp
                    <line x1="{{ $chartPad }}" x2="{{ $chartW - 10 }}" y1="{{ $yy }}" y2="{{ $yy }}" stroke="#eef0ee"/>
                @endfor
                @foreach ($chartMonths as $i => $row)
                    @php
                        $gx = $chartPad + $chartGw * $i + ($chartGw - $chartBw * $chartM - 4 * ($chartM - 1)) / 2;
                        $hL = ($chartH - 40) * ((float) $row['listings']) / $chartMax;
                        $hR = ($chartH - 40) * ((float) $row['requests']) / $chartMax;
                        $hU = ($chartH - 40) * ((float) $row['users']) / $chartMax;
                    @endphp
                    <rect x="{{ $gx }}" y="{{ $chartH - 26 - $hL }}" width="{{ $chartBw }}" height="{{ $hL }}" rx="5" fill="#087e82"/>
                    <rect x="{{ $gx + $chartBw + 4 }}" y="{{ $chartH - 26 - $hR }}" width="{{ $chartBw }}" height="{{ $hR }}" rx="5" fill="#c5985f"/>
                    <rect x="{{ $gx + ($chartBw + 4) * 2 }}" y="{{ $chartH - 26 - $hU }}" width="{{ $chartBw }}" height="{{ $hU }}" rx="5" fill="#9fb0c2"/>
                    <text x="{{ $chartPad + $chartGw * $i + $chartGw / 2 }}" y="{{ $chartH - 8 }}" text-anchor="middle" font-size="10.5" fill="#6b7280">{{ $row['label'] }}</text>
                @endforeach
            </svg>
            <p class="tiny">{{ __('aqdar.reports.range_note', ['from' => $filters['from'], 'to' => $filters['to']]) }}</p>
        </div>
        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.reports.available') }}</b></div>
            @foreach ($types as $type)
                <div class="dl" style="align-items:center;padding:10px 0">
                    <span style="display:flex;align-items:center;gap:8px">@include('dashboard::partials.icon', ['name' => 'doc'])<b>{{ __('aqdar.reports.types.'.$type) }}</b></span>
                    <span>
                        <a class="btn sm" style="background:var(--ok-soft);color:var(--ok)" href="{{ route('dashboard.reports.export', array_merge($filters, ['type' => $type])) }}">{{ __('aqdar.reports.csv') }}</a>
                        <a class="btn sm" style="background:var(--bad-soft);color:var(--bad)" href="{{ route('dashboard.reports.export', array_merge($filters, ['type' => $type, 'format' => 'pdf'])) }}">{{ __('aqdar.reports.pdf') }}</a>
                        @if ($canReveal)
                            <a class="btn sm ghost" href="{{ route('dashboard.reports.export', array_merge($filters, ['type' => $type, 'include_phones' => 1])) }}">{{ __('aqdar.reports.csv_with_phones') }}</a>
                            <a class="btn sm ghost" href="{{ route('dashboard.reports.export', array_merge($filters, ['type' => $type, 'format' => 'pdf', 'include_phones' => 1])) }}">{{ __('aqdar.reports.pdf_with_phones') }}</a>
                        @endif
                    </span>
                </div>
            @endforeach
            <p class="tiny" style="margin:8px 0 0">{{ __('aqdar.reports.privacy_note') }}</p>
        </div>
    </div>
@endsection
