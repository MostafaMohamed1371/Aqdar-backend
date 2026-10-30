@props(['key', 'label'])
@php
    $isActive = request('sort') === $key;
    $isAscending = $isActive && request('dir') === 'asc';
@endphp
<th>
    <a class="sort-th" href="{{ request()->fullUrlWithQuery(['sort' => $key, 'dir' => $isActive && ! $isAscending ? 'asc' : 'desc', 'page' => null]) }}" style="color:inherit;text-decoration:none;white-space:nowrap">
        {{ $label }}<span class="tiny" aria-hidden="true">{{ $isActive ? ($isAscending ? ' ↑' : ' ↓') : ' ↕' }}</span>
    </a>
</th>
