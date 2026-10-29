@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_02'))
@section('screen', 'ADM-02')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_02" /></h2>
        <p class="tiny">{{ __('aqdar.listings.desk.approve_publish_note') }}</p>
    </div>

    <form method="GET" action="{{ route('dashboard.listings.index') }}" class="toolbar">
        @if ($tab !== 'all')
            <input type="hidden" name="status" value="{{ $tab }}">
        @endif
        <select name="segment">
            <option value="">{{ __('aqdar.listings.desk.market') }}</option>
            <option value="non_distressed" @selected(($segment ?? '') === 'non_distressed')>{{ __('aqdar.listings.desk.non_distressed') }}</option>
            <option value="distressed" @selected(($segment ?? '') === 'distressed')>{{ __('aqdar.listings.desk.distressed') }}</option>
        </select>
        <label class="tiny" style="display:flex;gap:6px;align-items:center">
            <input type="checkbox" name="alerts" value="1" @checked($alerts ?? false)>
            {{ __('aqdar.listings.desk.alerts') }}
        </label>
        <div class="q">
            @include('dashboard::partials.icon', ['name' => 'search'])
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('aqdar.listings.desk.search') }}">
        </div>
        <button type="submit" class="btn ghost">{{ __('aqdar.search') }}</button>
    </form>

    <div class="ctabs">
        @foreach (['all', 'in_review', 'needs_changes', 'approved', 'published', 'hold', 'rejected', 'draft'] as $key)
            <a href="{{ route('dashboard.listings.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search ?: null, 'segment' => $segment ?: null, 'alerts' => !empty($alerts) ? 1 : null])) }}" @class(['on' => $tab === $key])>
                {{ __('aqdar.listings.desk.tab_'.$key) }}
                <em>{{ number_format($counts[$key] ?? 0) }}</em>
            </a>
        @endforeach
    </div>

    <div class="tw">
        <table class="t">
            <tr>
                <th></th>
                <th>{{ __('aqdar.listings.desk.title') }}</th>
                <th>{{ __('aqdar.listings.desk.market') }}</th>
                <th>{{ __('aqdar.listings.desk.governorate') }}</th>
                <th>{{ __('aqdar.listings.desk.developer') }}</th>
                <th>{{ __('aqdar.listings.desk.added') }}</th>
                <th>{{ __('aqdar.listings.desk.alerts') }}</th>
                <th>{{ __('structer.status') }}</th>
                <th>{{ __('structer.actions') }}</th>
            </tr>
            @forelse ($listings as $listing)
                <tr>
                    <td>
                        @php $cover = $listing->media->first(fn ($media) => $media->isApproved()); @endphp
                        @if ($cover?->publicUrl())
                            <img src="{{ $cover->publicUrl() }}" alt="" width="48" height="36" style="object-fit:cover;border-radius:8px">
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <b>{{ $listing->localizedTitle() ?: '—' }}</b>
                        <div class="tiny en">{{ $listing->public_code }}</div>
                    </td>
                    <td>
                        @if ($listing->segment?->value === 'distressed')
                            <span class="badge bad">{{ __('aqdar.listings.desk.distressed') }}{{ $listing->distressed_child ? ' · '.$listing->distressed_child->value : '' }}</span>
                        @else
                            <span class="badge ok">{{ __('aqdar.listings.desk.non_distressed') }}</span>
                        @endif
                    </td>
                    <td>{{ $listing->governorate?->localizedName() ?? '—' }}</td>
                    <td>{{ $listing->developer?->localizedName() ?? '—' }}</td>
                    <td class="en">{{ $listing->created_at?->toDateString() }}</td>
                    <td>
                        @if ($listing->contact_flagged)
                            <span class="badge bad">{{ __('aqdar.listings.desk.alert.'.$listing->contact_flag_reason) }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td><span class="badge {{ $listing->statusBadgeTone() }}">{{ __('aqdar.listings.status.'.$listing->status->value) }}</span></td>
                    <td style="white-space:nowrap">
                        <a class="btn ghost sm" href="{{ route('dashboard.listings.show', $listing) }}">{{ __('aqdar.listings.desk.view') }}</a>
                        @if ($listing->status->value === 'approved')
                            @can('publish_listing')
                                <form method="POST" action="{{ route('dashboard.listings.publish', $listing) }}" style="display:inline">
                                    @csrf
                                    <input type="hidden" name="reason" value="{{ __('aqdar.listings.desk.publish_default') }}">
                                    <button class="btn sm" type="submit">{{ __('aqdar.listings.desk.publish') }}</button>
                                </form>
                            @endcan
                        @endif
                        @if (in_array($listing->status->value, ['published', 'under_offer'], true))
                            @can('publish_listing')
                                <form method="POST" action="{{ route('dashboard.listings.hold', $listing) }}" style="display:inline">
                                    @csrf
                                    <input type="hidden" name="reason" value="{{ __('aqdar.listings.desk.hold_default') }}">
                                    <button class="btn ghost sm" type="submit">{{ __('aqdar.listings.desk.hold') }}</button>
                                </form>
                                <form method="POST" action="{{ route('dashboard.listings.archive', $listing) }}" style="display:inline">
                                    @csrf
                                    <input type="hidden" name="reason" value="{{ __('aqdar.listings.desk.archive_default') }}">
                                    <button class="btn ghost sm" type="submit">{{ __('aqdar.listings.desk.archive') }}</button>
                                </form>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">{{ __('aqdar.listings.desk.empty') }}</td>
                </tr>
            @endforelse
        </table>
    </div>
    {{ $listings->links() }}
@endsection
