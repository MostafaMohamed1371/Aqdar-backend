@props(['tab', 'routePrefix', 'records', 'slugPlaceholder'])

<div class="ptitle">
    <h2><x-bilingual key="aqdar.menu.adm_15" /></h2>
    <div class="acts">
        <button type="button" class="btn" onclick="const d = document.getElementById('new-record'); d.open = true; d.scrollIntoView({behavior: 'smooth'});">
            @include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.catalog.add_record') }}
        </button>
    </div>
</div>

@include('dashboard::partials.reference-tabs', ['tab' => $tab])

<details id="new-record" class="card" @if ($errors->any()) open @endif>
    <summary class="card-h"><b>{{ __('aqdar.catalog.add_record') }}</b></summary>
    <form method="POST" action="{{ route($routePrefix.'.store') }}" class="toolbar" style="flex-wrap:wrap">
        @csrf
        <input class="en" name="slug" placeholder="{{ $slugPlaceholder }}" required>
        <input name="name_ar" placeholder="{{ __('aqdar.catalog.name_ar') }}" required>
        <input class="en" name="name_en" placeholder="{{ __('aqdar.catalog.name_en') }}" required>
        <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
    </form>
</details>

<div class="tw">
    <table class="t">
        <tr>
            <th>{{ __('structer.name') }}</th>
            <th>{{ __('aqdar.catalog.code') }}</th>
            <th>{{ __('structer.status') }}</th>
            <th>{{ __('structer.actions') }}</th>
        </tr>
        @forelse ($records as $record)
            <tr>
                <td><b>{{ $record->localizedName() }}</b></td>
                <td class="tiny en">{{ $record->slug }}</td>
                <td>
                    <span @class(['badge', 'ok' => $record->is_active, 'grey' => ! $record->is_active])>
                        {{ $record->is_active ? __('structer.active') : __('structer.inactive') }}
                    </span>
                </td>
                <td style="white-space:nowrap">
                    <details style="display:inline-block">
                        <summary class="icon-btn" title="{{ __('structer.edit') }}" style="list-style:none;cursor:pointer">@include('dashboard::partials.icon', ['name' => 'edit'])</summary>
                        <form method="POST" action="{{ route($routePrefix.'.update', $record) }}" class="toolbar" style="flex-wrap:wrap;margin-top:6px">
                            @csrf @method('PUT')
                            <input name="name_ar" value="{{ $record->name_ar }}" required>
                            <input class="en" name="name_en" value="{{ $record->name_en }}" required>
                            <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
                        </form>
                    </details>
                    @include('dashboard::partials.catalog-toggle', [
                        'action' => route($routePrefix.'.update', $record),
                        'active' => $record->is_active,
                    ])
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="tiny">{{ __('aqdar.catalog.empty') }}</td></tr>
        @endforelse
    </table>
</div>
