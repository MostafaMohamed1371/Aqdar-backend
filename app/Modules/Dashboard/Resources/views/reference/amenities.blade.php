@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_15'))
@section('screen', 'ADM-15')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_15" /></h2>
    </div>
    @include('dashboard::partials.reference-tabs', ['tab' => 'amenities'])
    <form method="POST" action="{{ route('dashboard.amenities.store') }}" class="toolbar" style="flex-wrap:wrap">
        @csrf
        <input class="en" name="slug" placeholder="parking" required>
        <input name="name_ar" placeholder="{{ __('aqdar.catalog.name_ar') }}" required>
        <input class="en" name="name_en" placeholder="{{ __('aqdar.catalog.name_en') }}" required>
        <button class="btn" type="submit">{{ __('structer.save') }}</button>
    </form>
    <div class="tw">
        <table class="t">
            @foreach ($amenities as $amenity)
                <tr>
                    <td>
                        <form method="POST" action="{{ route('dashboard.amenities.update', $amenity) }}" class="toolbar" style="flex-wrap:wrap">
                            @csrf @method('PUT')
                            <input name="name_ar" value="{{ $amenity->name_ar }}" required>
                            <input class="en" name="name_en" value="{{ $amenity->name_en }}" required>
                            <button class="btn ghost sm" type="submit">{{ __('structer.save') }}</button>
                        </form>
                        <div class="tiny en">{{ $amenity->slug }}</div>
                    </td>
                    <td>
                        @if ($amenity->is_active)
                            <span class="badge ok">{{ __('structer.active') }}</span>
                        @else
                            <span class="badge grey">{{ __('structer.inactive') }}</span>
                        @endif
                    </td>
                    <td>
                        @include('dashboard::partials.catalog-toggle', [
                            'action' => route('dashboard.amenities.update', $amenity),
                            'active' => $amenity->is_active,
                        ])
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
