@php
    use App\Core\Enums\LeadPriority;
    use App\Core\Enums\LeadSource;
    use App\Core\Enums\LeadStatus;
    use App\Core\Identity\ContactMask;
    $tabs = ['all', 'new', 'contacted', 'qualified', 'visit_scheduled', 'offer_sent', 'converted', 'lost', 'overdue'];
    $pipeline = [
        ['new', '#14283f'],
        ['contacted', '#1d4a66'],
        ['qualified', '#087e82'],
        ['visit_scheduled', '#3a9a9d'],
        ['offer_sent', '#b0864f'],
        ['converted', '#c5985f'],
    ];
@endphp
@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_07'))
@section('screen', 'ADM-07')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_07" /></h2>
        <div class="acts">
            <a class="btn ghost" href="{{ route('dashboard.support.index') }}">{{ __('aqdar.support.title') }}</a>
            <a class="btn" href="#add-lead">@include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.crm.add') }}</a>
        </div>
    </div>
    <p class="tiny">{{ __('aqdar.crm.mask_note') }}</p>

    <div class="kpis k5">
        @foreach ([
            ['users', '', 'all', 'aqdar.crm.kpi.total'],
            ['star', 'info', 'new', 'aqdar.crm.kpi.new'],
            ['phone', 'warn', 'contacted', 'aqdar.crm.kpi.contacted'],
            ['calendar', 'gold', 'visit_scheduled', 'aqdar.crm.kpi.visit'],
            ['check', 'ok', 'converted', 'aqdar.crm.kpi.converted'],
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
                <span>{{ __('aqdar.crm.status.'.$key) }}<b>{{ number_format($counts[$key] ?? 0) }}</b></span>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('dashboard.leads.index') }}" class="filters">
        <div class="q">
            @include('dashboard::partials.icon', ['name' => 'search'])
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('aqdar.crm.search') }}">
        </div>
        <select name="source">
            <option value="">{{ __('aqdar.crm.all_sources') }}</option>
            @foreach (LeadSource::cases() as $src)
                <option value="{{ $src->value }}" @selected($source === $src->value)>{{ __('aqdar.crm.source.'.$src->value) }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="all">{{ __('aqdar.crm.all_stages') }}</option>
            @foreach (LeadStatus::cases() as $st)
                <option value="{{ $st->value }}" @selected($tab === $st->value)>{{ __('aqdar.crm.status.'.$st->value) }}</option>
            @endforeach
            <option value="overdue" @selected($tab === 'overdue')>{{ __('aqdar.crm.tab.overdue') }}</option>
        </select>
        @if ($canAssign)
            <select name="assigned_to">
                <option value="">{{ __('aqdar.crm.all_owners') }}</option>
                <option value="unassigned" @selected($assignedTo === 'unassigned')>{{ __('aqdar.crm.filter_unassigned') }}</option>
                @foreach ($assignable as $staff)
                    <option value="{{ $staff->id }}" @selected((string) $assignedTo === (string) $staff->id)>{{ $staff->name }}</option>
                @endforeach
            </select>
        @endif
        <button type="submit" class="btn ghost">{{ __('aqdar.search') }}</button>
    </form>

    <div class="ctabs">
        @foreach ($tabs as $key)
            <a href="{{ route('dashboard.leads.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search ?: null, 'source' => $source ?: null, 'assigned_to' => $assignedTo ?: null])) }}" @class(['on' => $tab === $key])>
                {{ __('aqdar.crm.tab.'.$key) }}
                <em>{{ number_format($counts[$key] ?? 0) }}</em>
            </a>
        @endforeach
    </div>

    <div class="tw">
        <table class="t">
            <tr>
                <th>{{ __('aqdar.crm.code') }}</th>
                <th>{{ __('aqdar.crm.buyer') }}</th>
                <th>{{ __('aqdar.users.phone') }}</th>
                <th>{{ __('aqdar.crm.source_label') }}</th>
                <th>{{ __('aqdar.crm.listing') }}</th>
                <th>{{ __('structer.status') }}</th>
                <th>{{ __('aqdar.crm.owner') }}</th>
                <th>{{ __('aqdar.crm.priority_label') }}</th>
                <th>{{ __('aqdar.crm.next_action') }}</th>
                <th>SLA</th>
                <th>{{ __('structer.actions') }}</th>
            </tr>
            @forelse ($leads as $lead)
                @php
                    $buyerPhone = $revealed[$lead->id]['buyer'] ?? null;
                @endphp
                <tr>
                    <td>
                        <b class="en">{{ $lead->public_code }}</b>
                        @if ($lead->is_duplicate)
                            <div class="tiny">{{ __('aqdar.crm.duplicate') }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="who">
                            <span class="av s c{{ ($lead->buyer_id % 6) + 1 }}">{{ mb_substr($lead->buyer?->name ?? '?', 0, 1) }}</span>
                            <div><b>{{ $lead->buyer?->name }}</b></div>
                        </div>
                    </td>
                    <td class="en" style="white-space:nowrap">
                        {{ $buyerPhone ?: ContactMask::phone($lead->buyer?->phone) }}
                        <form method="POST" action="{{ route('dashboard.leads.reveal', $lead) }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="party" value="buyer">
                            <button class="ib" type="submit" title="{{ __('aqdar.users.reveal_title') }}">@include('dashboard::partials.icon', ['name' => 'eye'])</button>
                        </form>
                    </td>
                    <td>{{ __('aqdar.crm.source.'.$lead->source?->value) }}</td>
                    <td class="en">{{ $lead->listing?->public_code ?? '—' }}</td>
                    <td><span class="badge {{ $lead->statusBadgeTone() }}">{{ __('aqdar.crm.status.'.$lead->status?->value) }}</span></td>
                    <td>
                        @if ($canAssign)
                            <form method="POST" action="{{ route('dashboard.leads.assign', $lead) }}">
                                @csrf
                                <select name="assigned_to" onchange="this.form.submit()">
                                    <option value="">{{ __('aqdar.crm.unassigned') }}</option>
                                    @foreach ($assignable as $staff)
                                        <option value="{{ $staff->id }}" @selected((int) $lead->assigned_to === (int) $staff->id)>{{ $staff->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @else
                            {{ $lead->assignee?->name ?? __('aqdar.crm.unassigned') }}
                        @endif
                    </td>
                    <td><span class="badge {{ $lead->priorityBadgeTone() }}">{{ __('aqdar.crm.priority.'.$lead->priority?->value) }}</span></td>
                    <td>{{ $lead->next_action ?: '—' }}</td>
                    <td>
                        @if ($lead->slaBreached())
                            <span class="badge bad">{{ __('aqdar.crm.sla_overdue_hours', ['hours' => $lead->slaOverdueHours()]) }}</span>
                        @elseif ($lead->status === LeadStatus::New && $lead->sla_due_at)
                            <span class="badge ok">{{ __('aqdar.crm.sla_ok') }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <a class="btn ghost sm" href="{{ route('dashboard.leads.show', $lead) }}">{{ __('aqdar.listings.desk.view') }}</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">{{ __('aqdar.crm.empty') }}</td>
                </tr>
            @endforelse
        </table>
    </div>
    {{ $leads->links() }}

    <div id="add-lead" class="card" style="margin-top:16px">
        <div class="card-h"><b>{{ __('aqdar.crm.add') }}</b></div>
        <form method="POST" action="{{ route('dashboard.leads.store') }}" class="toolbar" style="flex-wrap:wrap">
            @csrf
            <input type="number" name="buyer_id" required placeholder="{{ __('aqdar.crm.buyer_id') }}" min="1">
            <input type="number" name="listing_id" placeholder="{{ __('aqdar.crm.listing_id') }}" min="1">
            <select name="source" required>
                @foreach (LeadSource::cases() as $src)
                    <option value="{{ $src->value }}">{{ __('aqdar.crm.source.'.$src->value) }}</option>
                @endforeach
            </select>
            <select name="priority">
                @foreach (LeadPriority::cases() as $prio)
                    <option value="{{ $prio->value }}" @selected($prio === LeadPriority::Medium)>{{ __('aqdar.crm.priority.'.$prio->value) }}</option>
                @endforeach
            </select>
            @if ($canAssign)
                <select name="assigned_to">
                    <option value="">{{ __('aqdar.crm.unassigned') }}</option>
                    @foreach ($assignable as $staff)
                        <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                    @endforeach
                </select>
            @endif
            <input type="text" name="next_action" placeholder="{{ __('aqdar.crm.next_action') }}" value="first_call">
            <button class="btn" type="submit">@include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.crm.add') }}</button>
        </form>
    </div>
@endsection
