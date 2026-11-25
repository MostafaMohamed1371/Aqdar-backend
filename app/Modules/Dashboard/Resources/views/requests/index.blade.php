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
                <a href="{{ request()->fullUrlWithQuery(['type' => $key ?: null, 'page' => null]) }}" @class(['on' => ($type ?? '') === $key])>{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('dashboard.requests.index') }}" class="filters" style="flex:1;flex-wrap:wrap">
            @if ($tab !== 'all')<input type="hidden" name="status" value="{{ $tab }}">@endif
            @if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
            <div class="q">
                @include('dashboard::partials.icon', ['name' => 'search'])
                <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('aqdar.requests.search') }}">
            </div>
            @if ($canAssign)
                <select name="assigned_to" aria-label="{{ __('aqdar.crm.owner') }}">
                    <option value="">{{ __('aqdar.requests.filter.any_owner') }}</option>
                    <option value="unassigned" @selected($assignedTo === 'unassigned')>{{ __('aqdar.crm.unassigned') }}</option>
                    @foreach ($assignable as $staffMember)
                        <option value="{{ $staffMember->id }}" @selected($assignedTo === (string) $staffMember->id)>{{ $staffMember->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="governorate_id" aria-label="{{ __('aqdar.requests.region') }}">
                <option value="">{{ __('aqdar.requests.filter.any_region') }}</option>
                @foreach ($governorates as $governorate)
                    <option value="{{ $governorate->id }}" @selected($filters['governorate_id'] === (string) $governorate->id)>{{ app()->getLocale() === 'ar' ? $governorate->name_ar : $governorate->name_en }}</option>
                @endforeach
            </select>
            <select name="segment" aria-label="{{ __('aqdar.catalog.segment') }}">
                <option value="">{{ __('aqdar.requests.filter.any_segment') }}</option>
                @foreach (['any', 'non_distressed', 'distressed'] as $segment)
                    <option value="{{ $segment }}" @selected($filters['segment'] === $segment)>{{ __('aqdar.requests.segment.'.$segment) }}</option>
                @endforeach
            </select>
            <input type="number" name="budget_min" min="0" step="1000" value="{{ $filters['budget_min'] }}" placeholder="{{ __('aqdar.requests.filter.budget_from') }}" style="width:130px">
            <input type="number" name="budget_max" min="0" step="1000" value="{{ $filters['budget_max'] }}" placeholder="{{ __('aqdar.requests.filter.budget_to') }}" style="width:130px">
            @include('dashboard::partials.date-range')
            <label class="tiny" style="display:flex;align-items:center;gap:4px"><input type="checkbox" name="overdue" value="1" @checked($filters['overdue'])>{{ __('aqdar.requests.filter.overdue') }}</label>
            <label class="tiny" style="display:flex;align-items:center;gap:4px"><input type="checkbox" name="duplicates" value="1" @checked($filters['duplicates'])>{{ __('aqdar.requests.filter.duplicates') }}</label>
            <button class="btn ghost" type="submit">{{ __('aqdar.search') }}</button>
            <a class="btn ghost" href="{{ route('dashboard.requests.index') }}">{{ __('aqdar.queue.reset') }}</a>
        </form>
    </div>

    <div class="tw">
        <table class="t" data-live-table="requests">
            <tr>
                @include('dashboard::partials.sort-th', ['key' => 'code', 'label' => __('aqdar.crm.code')])
                <th>{{ __('aqdar.crm.buyer') }}</th>
                <th>{{ __('aqdar.listings.unit_type') }}</th>
                <th>{{ __('aqdar.requests.region') }}</th>
                @include('dashboard::partials.sort-th', ['key' => 'budget', 'label' => __('aqdar.listings.price')])
                <th>{{ __('aqdar.listings.area') }}</th>
                @include('dashboard::partials.sort-th', ['key' => 'status', 'label' => __('structer.status')])
                <th>{{ __('aqdar.crm.owner') }}</th>
                @include('dashboard::partials.sort-th', ['key' => 'created', 'label' => __('aqdar.queue.created')])
            </tr>
            @forelse ($requests as $item)
                <tr data-live-row="requests" data-live-id="{{ $item->id }}">
                    <td>
                        <a class="en" href="{{ route('dashboard.requests.show', $item) }}"><b>{{ $item->public_code }}</b></a>
                        @if ($item->is_duplicate)<span class="badge warn">{{ __('aqdar.requests.duplicate') }}</span>@endif
                    </td>
                    <td>{{ $item->buyer?->name }}</td>
                    <td>{{ $item->intent === BuyerIntent::Investment ? __('aqdar.requests.intent.investment') : ($item->unitType?->name_ar ?? $item->unitType?->name_en ?? '—') }}</td>
                    <td>{{ $item->area?->name_ar ?? $item->governorate?->name_ar ?? '—' }}</td>
                    <td class="en">{{ $item->budgetLabel() }}</td>
                    <td class="en">{{ $item->areaRangeLabel() }}</td>
                    <td><span class="badge {{ $item->statusBadgeTone() }}" data-live-field="status">{{ __('aqdar.requests.status.'.$item->status?->value) }}</span></td>
                    <td>{{ $item->assignee?->name ?? __('aqdar.crm.unassigned') }}</td>
                    <td class="en tiny">{{ $item->created_at?->format('Y/m/d') }}</td>
                </tr>
            @empty
                <tr data-live-empty="requests"><td colspan="9">{{ __('aqdar.requests.empty') }}</td></tr>
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
