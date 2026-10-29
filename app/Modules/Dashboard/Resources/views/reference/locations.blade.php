@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_15'))
@section('screen', 'ADM-15')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_15" /></h2>
    </div>

    @include('dashboard::partials.reference-tabs', ['tab' => 'locations'])

    <div class="cols2e">
        <div class="tw">
            <h3>{{ __('aqdar.catalog.governorates') }}</h3>
            <form method="POST" action="{{ route('dashboard.governorates.store') }}" class="toolbar" style="flex-wrap:wrap">
                @csrf
                <input class="en" name="slug" placeholder="cairo" required>
                <input name="name_ar" placeholder="{{ __('aqdar.catalog.name_ar') }}" required>
                <input class="en" name="name_en" placeholder="{{ __('aqdar.catalog.name_en') }}" required>
                <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
            </form>
            <table class="t">
                @foreach ($governorates as $governorate)
                    <tr>
                        <td>
                            <form method="POST" action="{{ route('dashboard.governorates.update', $governorate) }}" class="toolbar" style="flex-wrap:wrap">
                                @csrf @method('PUT')
                                <input name="name_ar" value="{{ $governorate->name_ar }}" required>
                                <input class="en" name="name_en" value="{{ $governorate->name_en }}" required>
                                <button class="btn ghost sm" type="submit">{{ __('structer.save') }}</button>
                            </form>
                            <div class="tiny en">{{ $governorate->slug }}</div>
                        </td>
                        <td>
                            @include('dashboard::partials.catalog-toggle', [
                                'action' => route('dashboard.governorates.update', $governorate),
                                'active' => $governorate->is_active,
                            ])
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
        <div class="tw">
            <h3>{{ __('aqdar.catalog.areas') }}</h3>
            <form method="POST" action="{{ route('dashboard.areas.store') }}" class="toolbar" style="flex-wrap:wrap">
                @csrf
                <select name="governorate_id" required>
                    @foreach ($governorates as $governorate)
                        <option value="{{ $governorate->id }}">{{ $governorate->localizedName() }}</option>
                    @endforeach
                </select>
                <input class="en" name="slug" placeholder="maadi" required>
                <input name="name_ar" placeholder="{{ __('aqdar.catalog.name_ar') }}" required>
                <input class="en" name="name_en" placeholder="{{ __('aqdar.catalog.name_en') }}" required>
                <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
            </form>
            <table class="t">
                @foreach ($governorates as $governorate)
                    @foreach ($governorate->areas as $area)
                        <tr>
                            <td>
                                <form method="POST" action="{{ route('dashboard.areas.update', $area) }}" class="toolbar" style="flex-wrap:wrap">
                                    @csrf @method('PUT')
                                    <input name="name_ar" value="{{ $area->name_ar }}" required>
                                    <input class="en" name="name_en" value="{{ $area->name_en }}" required>
                                    <button class="btn ghost sm" type="submit">{{ __('structer.save') }}</button>
                                </form>
                                <div class="tiny">{{ $governorate->localizedName() }} / {{ $area->slug }}</div>
                            </td>
                            <td>
                                @include('dashboard::partials.catalog-toggle', [
                                    'action' => route('dashboard.areas.update', $area),
                                    'active' => $area->is_active,
                                ])
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </table>
        </div>
    </div>
@endsection
