@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_15'))
@section('screen', 'ADM-15')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_15" /></h2>
        <div class="acts">
            <button type="button" class="btn" onclick="const d = document.getElementById('new-city'); d.open = true; d.scrollIntoView({behavior: 'smooth'});">
                @include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.catalog.add_city') }}
            </button>
        </div>
    </div>

    @include('dashboard::partials.reference-tabs', ['tab' => 'locations'])

    <details id="new-city" class="card" @if ($errors->any()) open @endif>
        <summary class="card-h"><b>{{ __('aqdar.catalog.add_city') }}</b></summary>
        <form method="POST" action="{{ route('dashboard.governorates.store') }}" class="toolbar" style="flex-wrap:wrap">
            @csrf
            <input class="en" name="slug" placeholder="cairo" required>
            <input class="en" name="code" placeholder="CAI" maxlength="8" style="width:80px">
            <input name="name_ar" placeholder="{{ __('aqdar.catalog.name_ar') }}" required>
            <input class="en" name="name_en" placeholder="{{ __('aqdar.catalog.name_en') }}" required>
            <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
        </form>
    </details>

    <div class="cols2e">
        <div class="tw">
            <table class="t">
                <tr>
                    <th>{{ __('aqdar.catalog.city_name') }}</th>
                    <th>{{ __('aqdar.catalog.code') }}</th>
                    <th>{{ __('aqdar.catalog.areas_count') }}</th>
                    <th>{{ __('structer.status') }}</th>
                    <th>{{ __('structer.actions') }}</th>
                </tr>
                @forelse ($governorates as $governorate)
                    <tr>
                        <td><b>{{ $governorate->localizedName() }}</b></td>
                        <td><b class="en">{{ $governorate->code ?? '—' }}</b></td>
                        <td class="en">{{ $governorate->areas_count }}</td>
                        <td>
                            <span @class(['badge', 'ok' => $governorate->is_active, 'grey' => ! $governorate->is_active])>
                                {{ $governorate->is_active ? __('structer.active') : __('structer.inactive') }}
                            </span>
                        </td>
                        <td style="white-space:nowrap">
                            <details style="display:inline-block">
                                <summary class="icon-btn" title="{{ __('structer.edit') }}" style="list-style:none;cursor:pointer">@include('dashboard::partials.icon', ['name' => 'edit'])</summary>
                                <form method="POST" action="{{ route('dashboard.governorates.update', $governorate) }}" class="toolbar" style="flex-wrap:wrap;margin-top:6px">
                                    @csrf @method('PUT')
                                    <input name="name_ar" value="{{ $governorate->name_ar }}" required>
                                    <input class="en" name="name_en" value="{{ $governorate->name_en }}" required>
                                    <input class="en" name="code" value="{{ $governorate->code }}" placeholder="{{ __('aqdar.catalog.code') }}" maxlength="8" style="width:80px">
                                    <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
                                </form>
                            </details>
                            @include('dashboard::partials.catalog-toggle', [
                                'action' => route('dashboard.governorates.update', $governorate),
                                'active' => $governorate->is_active,
                            ])
                            @if ($governorate->is_active)
                                <form method="POST" action="{{ route('dashboard.governorates.destroy', $governorate) }}" style="display:inline" onsubmit="return confirm(@js(__('aqdar.catalog.confirm_retire')))">
                                    @csrf @method('DELETE')
                                    <button class="btn bad sm" type="submit">{{ __('structer.delete') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="tiny">{{ __('aqdar.catalog.empty') }}</td></tr>
                @endforelse
            </table>
        </div>
        <div class="card" style="margin:0">
            <div class="card-h"><b>{{ __('aqdar.catalog.areas') }}</b></div>
            <details>
                <summary class="btn ghost sm" style="list-style:none;cursor:pointer;margin-bottom:8px">@include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.catalog.add_area') }}</summary>
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
            </details>
            @foreach ($governorates as $governorate)
                @foreach ($governorate->areas as $area)
                    <div class="dl" style="align-items:center">
                        <span><b>{{ $area->localizedName() }}</b> <span class="tiny">{{ $governorate->localizedName() }}</span></span>
                        <span style="display:flex;gap:6px;align-items:center">
                            <details>
                                <summary class="icon-btn" title="{{ __('structer.edit') }}" style="list-style:none;cursor:pointer">@include('dashboard::partials.icon', ['name' => 'edit'])</summary>
                                <form method="POST" action="{{ route('dashboard.areas.update', $area) }}" class="toolbar" style="flex-wrap:wrap;margin-top:6px">
                                    @csrf @method('PUT')
                                    <input name="name_ar" value="{{ $area->name_ar }}" required>
                                    <input class="en" name="name_en" value="{{ $area->name_en }}" required>
                                    <button class="btn sm" type="submit">{{ __('structer.save') }}</button>
                                </form>
                            </details>
                            @include('dashboard::partials.catalog-toggle', [
                                'action' => route('dashboard.areas.update', $area),
                                'active' => $area->is_active,
                            ])
                        </span>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
@endsection
