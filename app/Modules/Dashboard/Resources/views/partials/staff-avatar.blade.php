@php
    $staff = $staff ?? auth()->user();
    $size = $size ?? 's';
    $url = $staff?->avatarUrl();
    $pixels = match ($size) {
        'xl' => 84,
        'l' => 72,
        default => 32,
    };
@endphp
@if ($url)
    <img class="av {{ $size }}" src="{{ $url }}" alt="" width="{{ $pixels }}" height="{{ $pixels }}">
@else
    <span class="av {{ $size }} c{{ (($staff?->id ?? 0) % 6) + 1 }}">{{ mb_substr($staff?->name ?? '?', 0, 1) }}</span>
@endif
