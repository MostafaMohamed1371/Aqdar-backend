@php
    $tab = $tab ?? 'categories';
@endphp
<div class="toolbar" style="margin-bottom:16px">
    <a href="{{ route('dashboard.categories.index') }}" class="btn {{ $tab === 'categories' ? '' : 'ghost' }} sm">{{ __('aqdar.catalog.categories') }}</a>
    <a href="{{ route('dashboard.locations.index') }}" class="btn {{ $tab === 'locations' ? '' : 'ghost' }} sm">{{ __('aqdar.catalog.locations') }}</a>
    <a href="{{ route('dashboard.unit-types.index') }}" class="btn {{ $tab === 'unit-types' ? '' : 'ghost' }} sm">{{ __('aqdar.catalog.unit_types') }}</a>
    <a href="{{ route('dashboard.amenities.index') }}" class="btn {{ $tab === 'amenities' ? '' : 'ghost' }} sm">{{ __('aqdar.catalog.amenities') }}</a>
    <a href="{{ route('dashboard.tags.index') }}" class="btn {{ $tab === 'tags' ? '' : 'ghost' }} sm">{{ __('aqdar.catalog.tags') }}</a>
</div>
