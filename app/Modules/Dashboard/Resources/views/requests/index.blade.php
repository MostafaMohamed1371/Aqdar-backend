@php
    use App\Core\Enums\BuyerIntent;
    $pipeline = [
        ['new', '#14283f'],
        ['assigned', '#1d4a66'],
        ['contacting_buyer', '#087e82'],
        ['matching', '#3a9a9d'],
        ['options_shared', '#b0864f'],
        ['negotiation_visit', '#c5985f'],
        ['closed', '#8a6a32'],
    ];
@endphp
@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_08'))
@section('screen', 'ADM-08')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_08" /></h2>
        <div class="acts">
            <a class="btn" href="#add-request">@include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.requests.add') }}</a>
        </div>
    </div>

    <div class="kpis k5">
        @foreach ([
            ['inbox', '', 'all', 'aqdar.requests.kpi.total'],
            ['clock', 'warn', 'in_review', 'aqdar.requests.kpi.review'],
            ['phone', 'info', 'contacted', 'aqdar.requests.kpi.contacted'],
            ['send', 'gold', 'sent', 'aqdar.requests.kpi.sent'],
            ['check', 'ok', 'closed', 'aqdar.requests.kpi.closed'],
        ] as [$icon, $tone, $key, $label])
            <div class="kpi">
                <span class="ic {{ $tone }}">@include('dashboard::partials.icon', ['name' => $icon])</span>
                <div>
                    <span>{{ __($label) }}</span>
                    <b>{{ number_format($counts[$key] ?? 0) }}</b>
                </div>
            </div>
        @endforeach
    </div>

    <div class="toolbar">
        <div class="ctabs">
            @foreach (['' => __('aqdar.requests.all_types'), 'apartment' => __('aqdar.requests.type.apartment'), 'villa' => __('aqdar.requests.type.villa'), 'land' => __('aqdar.requests.type.land'), 'investment' => __('aqdar.requests.type.investment')] as $key => $label)
                <a href="{{ route('dashboard.requests.index', array_filter(['type' => $key ?: null, 'status' => $tab !== 'all' ? $tab : null, 'q' => $search ?: null])) }}" @class(['on' => ($type ?? '') === $key])>{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('dashboard.requests.index') }}" class="filters" style="flex:1">
            @if ($tab !== 'all')<input type="hidden" name="status" value="{{ $tab }}">@endif
            @if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
            <div class="q">
                @include('dashboard::partials.icon', ['name' => 'search'])
                <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('aqdar.requests.search') }}">
            </div>
            <button class="btn ghost" type="submit">{{ __('aqdar.search') }}</button>
        </form>
    </div>

    <div class="tw">
        <table class="t">
            <tr>
                <th>{{ __('aqdar.crm.code') }}</th>
                <th>{{ __('aqdar.crm.buyer') }}</th>
                <th>{{ __('aqdar.listings.unit_type') }}</th>
                <th>{{ __('aqdar.requests.region') }}</th>
                <th>{{ __('aqdar.listings.price') }}</th>
                <th>{{ __('aqdar.listings.area') }}</th>
                <th>{{ __('structer.status') }}</th>
                <th>{{ __('aqdar.crm.owner') }}</th>
            </tr>
            @forelse ($requests as $item)
                <tr>
                    <td><a class="en" href="{{ route('dashboard.requests.show', $item) }}"><b>{{ $item->public_code }}</b></a></td>
                    <td>{{ $item->buyer?->name }}</td>
                    <td>{{ $item->intent === BuyerIntent::Investment ? __('aqdar.requests.intent.investment') : ($item->unitType?->name_ar ?? $item->unitType?->name_en ?? '—') }}</td>
                    <td>{{ $item->area?->name_ar ?? $item->governorate?->name_ar ?? '—' }}</td>
                    <td class="en">{{ $item->budgetLabel() }}</td>
                    <td class="en">{{ $item->areaRangeLabel() }}</td>
                    <td><span class="badge {{ $item->statusBadgeTone() }}">{{ __('aqdar.requests.status.'.$item->status?->value) }}</span></td>
                    <td>{{ $item->assignee?->name ?? __('aqdar.crm.unassigned') }}</td>
                </tr>
            @empty
                <tr><td colspan="8">{{ __('aqdar.requests.empty') }}</td></tr>
            @endforelse
        </table>
    </div>
    {{ $requests->links() }}

    <div class="chevs">
        @foreach ($pipeline as [$key, $color])
            <div style="background:{{ $color }}">
                <a href="{{ route('dashboard.requests.index', array_filter(['status' => $key, 'type' => $type ?: null, 'q' => $search ?: null])) }}" style="color:inherit;text-decoration:none">
                    <span>{{ $key === 'closed' ? __('aqdar.requests.status.converted_or_closed') : __('aqdar.requests.status.'.$key) }}<b>{{ number_format($counts[$key] ?? 0) }}</b></span>
                </a>
            </div>
        @endforeach
    </div>

    <div id="add-request" class="card" style="margin-top:16px">
        <div class="card-h"><b>{{ __('aqdar.requests.add') }}</b></div>
        <form method="POST" action="{{ route('dashboard.requests.store') }}" class="toolbar" style="flex-wrap:wrap">
            @csrf
            <input type="number" name="buyer_id" required min="1" placeholder="{{ __('aqdar.crm.buyer_id') }}">
            <select name="intent" required>
                @foreach (BuyerIntent::cases() as $intent)
                    <option value="{{ $intent->value }}">{{ __('aqdar.requests.intent.'.$intent->value) }}</option>
                @endforeach
            </select>
            <input type="number" name="budget_max" step="0.01" placeholder="{{ __('aqdar.listings.price') }}">
            <button class="btn" type="submit">@include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.requests.add') }}</button>
        </form>
    </div>
    <p class="tiny">{{ __('aqdar.requests.open_without_matches') }}</p>
@endsection
