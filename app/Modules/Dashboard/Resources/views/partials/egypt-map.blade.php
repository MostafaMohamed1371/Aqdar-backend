@php
    $egypt = $charts['egypt'] ?? [];
@endphp
<div class="card" data-chart="egypt-map">
    <div class="card-h"><b>{{ __('aqdar.charts.egypt_distribution') }}</b></div>
    <svg viewBox="0 0 300 260" width="100%" height="250" style="direction:ltr;display:block">
        <rect class="chart-map-bg" width="300" height="260" rx="12"/>
        <path class="chart-map-land" d="M22 34 L120 30 L150 40 L196 34 L212 40 L222 34 L262 96 L246 118 L224 74 L214 64 L230 104 L262 168 L290 248 L22 248 Z" stroke-width="1.5"/>
        <path d="M150 248 C 158 200, 140 170, 152 130 S 146 80, 150 52 M150 52 L132 38 M150 52 L168 38" stroke="#8ec6d8" stroke-width="3.5" fill="none" stroke-linecap="round"/>
        @foreach ([[150, 60], [118, 44], [142, 66], [200, 150], [180, 220], [90, 120]] as $i => [$px, $py])
            <g transform="translate({{ $px }},{{ $py }})">
                <path d="M0 0 C -7 -9 -7 -17 0 -17 C 7 -17 7 -9 0 0Z" fill="{{ $i % 2 ? '#c5985f' : '#14283f' }}"/>
                <circle cy="-11.5" r="2.4" fill="#fff"/>
            </g>
        @endforeach
        @foreach ($egypt as $callout)
            <g transform="translate({{ $callout['x'] }},{{ $callout['y'] }})">
                <rect class="chart-callout" width="74" height="34" rx="7"/>
                <text class="chart-muted" x="37" y="14" text-anchor="middle" font-size="9">{{ __('aqdar.charts.egypt.'.$callout['key']) }}</text>
                <text class="chart-fg" x="37" y="27" text-anchor="middle" font-size="11" font-weight="800">{{ number_format((int) $callout['count']) }}</text>
            </g>
        @endforeach
    </svg>
</div>
