@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_15'))
@section('screen', 'ADM-15')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_15" /></h2>
        <div class="acts">
            <a href="{{ route('dashboard.categories.create') }}" class="btn">
                @include('dashboard::partials.icon', ['name' => 'plus'])
                {{ __('structer.add_category') }}
            </a>
        </div>
    </div>

    @include('dashboard::partials.reference-tabs', ['tab' => 'categories'])

    <p class="empty-note">{{ __('aqdar.catalog.distressed_note') }}</p>

    <div class="tw">
        <table class="t">
            <tr>
                <th>{{ __('structer.name') }}</th>
                <th>{{ __('structer.slug') }}</th>
                <th>{{ __('aqdar.catalog.segment') }}</th>
                <th>{{ __('aqdar.catalog.help_ar') }}</th>
                <th>{{ __('aqdar.catalog.commission_policy_code') }}</th>
                <th>{{ __('structer.status') }}</th>
                <th>{{ __('structer.actions') }}</th>
            </tr>
            @forelse ($categories as $category)
                <tr>
                    <td>{{ $category->localizedName() }}</td>
                    <td class="en">{{ $category->slug }}</td>
                    <td class="en">{{ $category->segment?->value ?? $category->kind?->value }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($category->help_ar ?: $category->help_en, 80) }}</td>
                    <td class="en">{{ $category->commission_policy_code ?: '—' }}</td>
                    <td>
                        @if ($category->is_active)
                            <span class="badge ok">{{ __('structer.active') }}</span>
                        @else
                            <span class="badge grey">{{ __('structer.inactive') }}</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('dashboard.categories.edit', $category) }}" class="btn ghost sm">{{ __('structer.edit') }}</a>
                        @unless ($category->isProtected())
                            @include('dashboard::partials.catalog-toggle', [
                                'action' => route('dashboard.categories.update', $category),
                                'active' => $category->is_active,
                            ])
                        @endunless
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <p class="empty-note">{{ __('structer.no_categories_yet') }}</p>
                    </td>
                </tr>
            @endforelse
        </table>
    </div>
@endsection
