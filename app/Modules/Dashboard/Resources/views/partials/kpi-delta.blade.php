@php
    $delta = $deltas[$key] ?? null;
@endphp
@if (is_array($delta))
    <span class="d {{ ! empty($delta['down']) ? 'dn' : '' }}">{{ ! empty($delta['down']) ? '↓' : '↑' }} {{ number_format((float) $delta['pct'], 0) }}%</span>
@endif
