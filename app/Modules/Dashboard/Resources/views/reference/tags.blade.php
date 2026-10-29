@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_15'))
@section('screen', 'ADM-15')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_15" /></h2>
    </div>
    @include('dashboard::partials.reference-tabs', ['tab' => 'tags'])
    <form method="POST" action="{{ route('dashboard.tags.store') }}" class="toolbar" style="flex-wrap:wrap">
        @csrf
        <input class="en" name="slug" placeholder="featured" required>
        <input name="name_ar" placeholder="{{ __('aqdar.catalog.name_ar') }}" required>
        <input class="en" name="name_en" placeholder="{{ __('aqdar.catalog.name_en') }}" required>
        <button class="btn" type="submit">{{ __('structer.save') }}</button>
    </form>
    <div class="tw">
        <table class="t">
            @foreach ($tags as $tag)
                <tr>
                    <td>
                        <form method="POST" action="{{ route('dashboard.tags.update', $tag) }}" class="toolbar" style="flex-wrap:wrap">
                            @csrf @method('PUT')
                            <input name="name_ar" value="{{ $tag->name_ar }}" required>
                            <input class="en" name="name_en" value="{{ $tag->name_en }}" required>
                            <button class="btn ghost sm" type="submit">{{ __('structer.save') }}</button>
                        </form>
                        <div class="tiny en">{{ $tag->slug }}</div>
                    </td>
                    <td>
                        @if ($tag->is_active)
                            <span class="badge ok">{{ __('structer.active') }}</span>
                        @else
                            <span class="badge grey">{{ __('structer.inactive') }}</span>
                        @endif
                    </td>
                    <td>
                        @include('dashboard::partials.catalog-toggle', [
                            'action' => route('dashboard.tags.update', $tag),
                            'active' => $tag->is_active,
                        ])
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
