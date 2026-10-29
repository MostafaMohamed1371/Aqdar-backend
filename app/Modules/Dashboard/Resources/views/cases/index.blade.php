@php
    use App\Core\Enums\CaseStage;
    use App\Core\Enums\CaseStatus;
    $pipeline = [
        ['new_client', '#14283f'],
        ['qualify_buyer', '#1d4a66'],
        ['contact_seller', '#245d79'],
        ['documents_complete', '#087e82'],
        ['align_parties', '#3a9a9d'],
        ['developer_approval', '#b0864f'],
        ['schedule_transfer', '#c5985f'],
        ['completed', '#8a6a32'],
    ];
@endphp
@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_09'))
@section('screen', 'ADM-09')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_09" /></h2>
        <div class="acts">
            <a class="btn" href="#add-case">@include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.cases.add') }}</a>
        </div>
    </div>
    <p class="tiny">{{ __('aqdar.cases.manual_docs') }}</p>

    <div class="kpis k5">
        @foreach ([
            ['swap', '', 'all', 'aqdar.cases.kpi.total'],
            ['doc', 'warn', 'documents', 'aqdar.cases.kpi.documents'],
            ['users', 'info', 'align', 'aqdar.cases.kpi.align'],
            ['building', 'gold', 'developer', 'aqdar.cases.kpi.developer'],
            ['check', 'ok', 'completed', 'aqdar.cases.kpi.completed'],
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

    <div class="chevs">
        @foreach ($pipeline as [$key, $color])
            <div style="background:{{ $color }}">
                <a href="{{ route('dashboard.cases.index', array_filter(['stage' => $key, 'status' => $status ?: null, 'assigned_to' => $assignedTo ?: null, 'q' => $search ?: null])) }}" style="color:inherit;text-decoration:none">
                    <span>{{ __('aqdar.cases.stage.'.$key) }}<b>{{ number_format($counts[$key] ?? 0) }}</b></span>
                </a>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('dashboard.cases.index') }}" class="filters">
        <div class="q">
            @include('dashboard::partials.icon', ['name' => 'search'])
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('aqdar.cases.search') }}">
        </div>
        <select name="stage">
            <option value="all">{{ __('aqdar.cases.all_stages') }}</option>
            @foreach (CaseStage::cases() as $stage)
                <option value="{{ $stage->value }}" @selected($tab === $stage->value)>{{ __('aqdar.cases.stage.'.$stage->value) }}</option>
            @endforeach
        </select>
        @if ($canAssign)
            <select name="assigned_to">
                <option value="">{{ __('aqdar.crm.all_owners') }}</option>
                @foreach ($assignable as $staff)
                    <option value="{{ $staff->id }}" @selected((string) $assignedTo === (string) $staff->id)>{{ $staff->name }}</option>
                @endforeach
            </select>
        @endif
        <select name="status">
            <option value="">{{ __('aqdar.cases.lifecycle_filter') }}</option>
            @foreach (CaseStatus::cases() as $st)
                <option value="{{ $st->value }}" @selected($status === $st->value)>{{ __('aqdar.cases.lifecycle.'.$st->value) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn ghost">{{ __('aqdar.search') }}</button>
    </form>

    <div class="tw">
        <table class="t">
            <tr>
                <th>{{ __('aqdar.crm.code') }}</th>
                <th>{{ __('aqdar.crm.listing') }}</th>
                <th>{{ __('aqdar.crm.buyer') }}</th>
                <th>{{ __('aqdar.cases.seller') }}</th>
                <th>{{ __('aqdar.cases.internal_stage') }}</th>
                <th>{{ __('aqdar.cases.lifecycle_column') }}</th>
                <th>{{ __('aqdar.cases.client_sees') }}</th>
                <th>{{ __('aqdar.crm.owner') }}</th>
                <th>{{ __('aqdar.cases.missing_docs') }}</th>
                <th>{{ __('aqdar.cases.updated') }}</th>
            </tr>
            @forelse ($cases as $item)
                <tr>
                    <td><a class="en" href="{{ route('dashboard.cases.show', $item) }}"><b>{{ $item->public_code }}</b></a></td>
                    <td>{{ $item->listing?->localizedTitle() ?? '—' }}</td>
                    <td>{{ $item->buyer?->name }}</td>
                    <td>{{ $item->seller?->name ?? '—' }}</td>
                    <td><span class="badge {{ $item->stageBadgeTone() }}">{{ __('aqdar.cases.stage.'.$item->internal_stage?->value) }}</span></td>
                    <td><span class="badge {{ $item->lifecycleBadgeTone() }}">{{ __('aqdar.cases.lifecycle.'.$item->status?->value) }}</span></td>
                    <td>{{ __('aqdar.cases.client.'.$item->client_label) }}</td>
                    <td>{{ $item->assignee?->name ?? __('aqdar.crm.unassigned') }}</td>
                    <td class="en">{{ $item->missing_documents_count }}</td>
                    <td class="en">{{ $item->last_transition_at?->format('Y/m/d') ?? $item->updated_at?->format('Y/m/d') }}</td>
                </tr>
            @empty
                    <tr><td colspan="10">{{ __('aqdar.cases.empty') }}</td></tr>
            @endforelse
        </table>
    </div>
    {{ $cases->links() }}

    <div id="add-case" class="card" style="margin-top:16px">
        <div class="card-h"><b>{{ __('aqdar.cases.add') }}</b></div>
        <form method="POST" action="{{ route('dashboard.cases.store') }}" class="toolbar" style="flex-wrap:wrap">
            @csrf
            <input type="number" name="buyer_id" required min="1" placeholder="{{ __('aqdar.crm.buyer_id') }}">
            <input type="number" name="listing_id" min="1" placeholder="{{ __('aqdar.crm.listing') }}">
            <input type="hidden" name="reason" value="{{ __('aqdar.crm.convert_default') }}">
            <button class="btn" type="submit">@include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.cases.add') }}</button>
        </form>
    </div>
@endsection
