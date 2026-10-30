@php
    use App\Core\Enums\ContactedParty;
    use App\Core\Enums\LeadActivityType;
    use App\Core\Enums\AppointmentStatus;
    use App\Core\Enums\LeadStatus;
    use App\Core\Identity\ContactMask;
    $buyerPhone = $revealed['buyer'] ?? null;
    $sellerPhone = $revealed['seller'] ?? null;
@endphp
@extends('dashboard::layouts.app')

@section('title', $lead->public_code)
@section('screen', 'ADM-07')

@section('content')
    <div class="ptitle">
        <h2>{{ $lead->public_code }} · {{ $lead->buyer?->name }}</h2>
        <div class="acts">
            <a class="btn ghost" href="{{ route('dashboard.leads.index') }}">{{ __('aqdar.listings.desk.back') }}</a>
            <span class="badge {{ $lead->statusBadgeTone() }}">{{ __('aqdar.crm.status.'.$lead->status?->value) }}</span>
        </div>
    </div>

    <p class="tiny">{{ __('aqdar.crm.mask_note') }}</p>

    <div class="cols2e">
        <div class="card">
            <div class="card-h">
                <b>{{ __('aqdar.crm.details') }} {{ $lead->public_code }}</b>
                <span class="badge ok">{{ __('aqdar.crm.consent_ok') }}</span>
            </div>
            <div class="dl"><span>{{ __('aqdar.crm.buyer') }}</span><b>{{ $lead->buyer?->name }}</b></div>
            <div class="dl">
                <span>{{ __('aqdar.users.phone') }}</span>
                <b class="en">
                    {{ $buyerPhone ?: ContactMask::phone($lead->buyer?->phone) }}
                    <form method="POST" action="{{ route('dashboard.leads.reveal', $lead) }}" style="display:inline">
                        @csrf
                        <input type="hidden" name="party" value="buyer">
                        <button class="ib" type="submit">@include('dashboard::partials.icon', ['name' => 'eye'])</button>
                    </form>
                </b>
            </div>
            <div class="dl">
                <span>{{ __('aqdar.crm.linked_seller') }}</span>
                <b>
                    {{ $lead->seller ? '#S-'.$lead->seller_id : '—' }}
                    @if ($lead->seller)
                        {{ $sellerPhone ?: ContactMask::phone($lead->seller->phone) }}
                        <form method="POST" action="{{ route('dashboard.leads.reveal', $lead) }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="party" value="seller">
                            <button class="ib" type="submit">@include('dashboard::partials.icon', ['name' => 'eye'])</button>
                        </form>
                    @endif
                </b>
            </div>
            <div class="dl"><span>{{ __('aqdar.crm.listing') }}</span><b class="en">{{ $lead->listing?->public_code ?? '—' }}</b></div>
            <div class="dl"><span>{{ __('aqdar.crm.source_label') }}</span><b>{{ __('aqdar.crm.source.'.$lead->source?->value) }}</b></div>
            <div class="dl">
                <span>{{ __('aqdar.crm.owner') }}</span>
                <b>
                    {{ $lead->assignee?->name ?? __('aqdar.crm.unassigned') }}
                    @if ($canAssign)
                        @include('dashboard::partials.assign-select', ['action' => route('dashboard.leads.assign', $lead), 'current' => $lead->assigned_to, 'assignable' => $assignable, 'inline' => true])
                    @endif
                </b>
            </div>
            <div class="dl"><span>{{ __('aqdar.crm.last_contact') }}</span><b class="en">{{ $lead->last_contacted_at?->format('Y/m/d H:i') ?? '—' }}</b></div>
            <div class="dl"><span>{{ __('aqdar.crm.duplicate') }}</span><b>{{ $lead->is_duplicate ? __('aqdar.crm.duplicate_yes') : __('aqdar.crm.duplicate_no') }}</b></div>
            @if ($lead->lost_reason)
                <div class="dl"><span>{{ __('aqdar.crm.lost_reason') }}</span><b>{{ $lead->lost_reason }}</b></div>
            @endif
            <div class="dl">
                <span>{{ __('aqdar.crm.open_case') }}</span>
                @if ($lead->status !== LeadStatus::Converted && $lead->status !== LeadStatus::Lost)
                    <form method="POST" action="{{ route('dashboard.leads.status', $lead) }}">
                        @csrf
                        <input type="hidden" name="status" value="converted">
                        <input type="hidden" name="reason" value="{{ __('aqdar.crm.convert_default') }}">
                        <button class="btn sm" type="submit">@include('dashboard::partials.icon', ['name' => 'swap']){{ __('aqdar.crm.open_case') }}</button>
                    </form>
                @else
                    <b>{{ __('aqdar.crm.status.'.$lead->status?->value) }}</b>
                    @foreach ($lead->transferCases as $case)
                        <div><a class="btn ghost sm" href="{{ route('dashboard.cases.show', $case) }}">{{ $case->public_code }}</a></div>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.crm.activity') }}</b></div>
            <form method="POST" action="{{ route('dashboard.leads.activity', $lead) }}" style="padding:0 14px 12px;display:flex;flex-direction:column;gap:8px">
                @csrf
                <select name="type" required>
                    @foreach (LeadActivityType::cases() as $type)
                        <option value="{{ $type->value }}">{{ __('aqdar.crm.activity_type.'.$type->value) }}</option>
                    @endforeach
                </select>
                <select name="contacted_party">
                    <option value="">{{ __('aqdar.crm.party_none') }}</option>
                    @foreach (ContactedParty::cases() as $party)
                        <option value="{{ $party->value }}">{{ __('aqdar.crm.party.'.$party->value) }}</option>
                    @endforeach
                </select>
                <textarea name="body" rows="3" required placeholder="{{ __('aqdar.crm.activity_body') }}"></textarea>
                <input type="datetime-local" name="due_at">
                <input type="number" step="0.01" name="seller_visible_amount" placeholder="{{ __('aqdar.crm.seller_visible_amount') }}">
                <input type="text" name="next_action" placeholder="{{ __('aqdar.crm.next_action') }}">
                <button class="btn ghost sm" type="submit">@include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.crm.add_activity') }}</button>
            </form>
            @forelse ($lead->activities as $activity)
                <div class="dl">
                    <span>@include('dashboard::partials.icon', ['name' => $activity->type?->value === 'task' ? 'calendar' : ($activity->type?->value === 'call' ? 'phone' : 'chat')]) {{ $activity->body }}</span>
                    <b class="en tiny">{{ $activity->created_at?->format('Y/m/d H:i') }} · {{ $activity->actor?->name }}</b>
                </div>
            @empty
                <p class="tiny" style="padding:0 14px">{{ __('aqdar.crm.no_activity') }}</p>
            @endforelse
        </div>
    </div>

    <div class="card" style="margin-top:14px">
        <div class="card-h"><b>{{ __('aqdar.crm.stage') }}</b></div>
        <form method="POST" action="{{ route('dashboard.leads.status', $lead) }}" class="toolbar">
            @csrf
            <select name="status">
                @foreach (LeadStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($lead->status === $status)>{{ __('aqdar.crm.status.'.$status->value) }}</option>
                @endforeach
            </select>
            <input type="text" name="reason" required minlength="3" placeholder="{{ __('aqdar.crm.status_default') }}" value="{{ __('aqdar.crm.status_default') }}">
            <button class="btn sm" type="submit">{{ __('aqdar.crm.update_status') }}</button>
        </form>
    </div>

    @if ($lead->appointments->isNotEmpty())
        <div class="card" style="margin-top:14px">
            <div class="card-h"><b>{{ __('aqdar.crm.appointments') }}</b></div>
            @foreach ($lead->appointments as $appointment)
                <div class="dl">
                    <span class="en">{{ $appointment->public_code }} · {{ $appointment->requested_date?->toDateString() }} {{ $appointment->requested_slot }}</span>
                    <b>
                        {{ __('aqdar.crm.appointment.'.$appointment->status?->value) }}
                        @if ($appointment->status === AppointmentStatus::Requested)
                            <form method="POST" action="{{ route('dashboard.leads.appointments.status', [$lead, $appointment]) }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="status" value="confirmed">
                                <button class="btn sm" type="submit">{{ __('aqdar.crm.confirm_appointment') }}</button>
                            </form>
                            <form method="POST" action="{{ route('dashboard.leads.appointments.status', [$lead, $appointment]) }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="status" value="cancelled">
                                <button class="btn ghost sm" type="submit">{{ __('aqdar.crm.cancel_appointment') }}</button>
                            </form>
                        @endif
                    </b>
                </div>
            @endforeach
        </div>
    @endif
@endsection
