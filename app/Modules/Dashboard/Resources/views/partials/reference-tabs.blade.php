@php
    $tab = $tab ?? 'categories';
    $referenceTabs = [
        'developers' => [route('dashboard.developers.index'), __('aqdar.catalog.developers')],
        'projects' => [route('dashboard.developers.index', ['tab' => 'projects']), __('aqdar.catalog.projects')],
        'locations' => [route('dashboard.locations.index'), __('aqdar.catalog.locations')],
        'unit-types' => [route('dashboard.unit-types.index'), __('aqdar.catalog.unit_types')],
        'categories' => [route('dashboard.categories.index'), __('aqdar.catalog.categories')],
        'amenities' => [route('dashboard.amenities.index'), __('aqdar.catalog.amenities')],
        'tags' => [route('dashboard.tags.index'), __('aqdar.catalog.tags')],
    ];
@endphp
<div class="ctabs">
    @foreach ($referenceTabs as $key => [$href, $label])
        <a href="{{ $href }}" @class(['on' => $tab === $key])>
            {{ $label }}
            <em>{{ number_format($referenceCounts[$key] ?? 0) }}</em>
        </a>
    @endforeach
</div>
