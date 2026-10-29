@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_15'))
@section('screen', 'ADM-15')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_15" /></h2>
    </div>
    @include('dashboard::partials.reference-tabs', ['tab' => 'unit-types'])
    <form method="POST" action="{{ route('dashboard.unit-types.store') }}" class="toolbar" style="flex-wrap:wrap">
        @csrf
        <input class="en" name="slug" placeholder="apartment" required>
        <input name="name_ar" placeholder="{{ __('aqdar.catalog.name_ar') }}" required>
        <input class="en" name="name_en" placeholder="{{ __('aqdar.catalog.name_en') }}" required>
        <button class="btn" type="submit">{{ __('structer.save') }}</button>
    </form>
    <div class="tw">
        <table class="t">
            @foreach ($unitTypes as $unitType)
                <tr>
                    <td>
                        <form method="POST" action="{{ route('dashboard.unit-types.update', $unitType) }}" class="toolbar" style="flex-wrap:wrap">
                            @csrf @method('PUT')
                            <input name="name_ar" value="{{ $unitType->name_ar }}" required>
                            <input class="en" name="name_en" value="{{ $unitType->name_en }}" required>
                            <button class="btn ghost sm" type="submit">{{ __('structer.save') }}</button>
                        </form>
                        <div class="tiny en">{{ $unitType->slug }}</div>
                    </td>
                    <td>
                        @if ($unitType->is_active)
                            <span class="badge ok">{{ __('structer.active') }}</span>
                        @else
                            <span class="badge grey">{{ __('structer.inactive') }}</span>
                        @endif
                    </td>
                    <td>
                        @include('dashboard::partials.catalog-toggle', [
                            'action' => route('dashboard.unit-types.update', $unitType),
                            'active' => $unitType->is_active,
                        ])
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
