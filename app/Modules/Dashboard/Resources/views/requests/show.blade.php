@php
    use App\Core\Enums\BuyerRequestStatus;
    use App\Core\Enums\MatchBuyerResponse;
    $item = $requestModel;
@endphp
@extends('dashboard::layouts.app')

@section('title', $item->public_code)
@section('screen', 'ADM-08')

@section('content')
    <div class="ptitle">
        <h2>{{ __('aqdar.requests.matches') }} {{ $item->public_code }} · {{ $item->buyer?->name }}</h2>
        <div class="acts">
            <a class="btn ghost" href="{{ route('dashboard.requests.index') }}">{{ __('aqdar.listings.desk.back') }}</a>
            <span class="badge {{ $item->statusBadgeTone() }}">{{ __('aqdar.requests.status.'.$item->status?->value) }}</span>
        </div>
    </div>
    <p class="tiny">
        {{ $item->unitType?->name_ar }} · {{ $item->area?->name_ar }} · {{ $item->areaRangeLabel() }} {{ __('aqdar.listings.area') }} · {{ $item->budgetLabel() }}
        @if ($item->down_payment)
            · {{ __('aqdar.requests.down_payment') }} {{ number_format((float) $item->down_payment) }}
        @endif
        @if ($item->monthly_installment)
            · {{ __('aqdar.requests.installment') }} {{ number_format((float) $item->monthly_installment) }}
        @endif
    </p>
    @if ($item->is_duplicate && $item->duplicateOf)
        <div class="alert" style="background:var(--warn-soft);color:var(--warn)">
            <a href="{{ route('dashboard.requests.show', $item->duplicateOf) }}">{{ __('aqdar.requests.duplicate_of', ['code' => $item->duplicateOf->public_code]) }}</a>
        </div>
    @endif

    @php
        $nextFollowUp = $item->lead?->activities->whereNotNull('due_at')->sortByDesc('due_at')->first()?->due_at ?? $item->lead?->next_action_at;
    @endphp
    <div class="card" style="margin-bottom:14px">
        <div class="card-h"><b>{{ __('aqdar.requests.details') }}</b></div>
        <div class="tw">
            <table class="t">
                <tr><th>{{ __('aqdar.requests.intent.'.$item->intent?->value) }}</th><td>{{ __('aqdar.requests.segment.'.($item->segment_preference?->value ?? 'any')) }} · {{ $item->bedrooms ? $item->bedrooms.' BR' : '—' }} · {{ $item->finishing ?? '—' }}</td></tr>
                <tr><th>{{ __('aqdar.requests.timeline_label') }}</th><td>{{ $item->timeline ?? '—' }}</td></tr>
                <tr><th>{{ __('aqdar.requests.priorities') }}</th><td>{{ $item->priorities ?: '—' }}</td></tr>
                <tr><th>{{ __('aqdar.requests.contact_method') }}</th><td>{{ $item->contact_method ? __('aqdar.requests.contact_methods.'.$item->contact_method->value) : '—' }}</td></tr>
                <tr><th>{{ __('aqdar.requests.consent') }}</th><td class="en">{{ $item->consent_at?->format('Y/m/d H:i') ?? '—' }}</td></tr>
                <tr><th>{{ __('aqdar.requests.source') }}</th><td>{{ $item->lead?->source ? __('aqdar.crm.source.'.$item->lead->source->value) : '—' }}</td></tr>
                <tr><th>{{ __('aqdar.requests.priority') }}</th><td>@if ($item->lead?->priority)<span class="badge {{ $item->lead->priorityBadgeTone() }}">{{ __('aqdar.crm.priority.'.$item->lead->priority->value) }}</span>@else — @endif</td></tr>
                <tr><th>{{ __('aqdar.requests.next_followup') }}</th><td class="en">
                    {{ $nextFollowUp?->format('Y/m/d H:i') ?? '—' }}
                    @if ($item->lead?->hasOverdueFollowUp())
                        <span class="badge bad">{{ __('aqdar.requests.filter.overdue') }}</span>
                    @endif
                </td></tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-h">
            <b>{{ __('aqdar.crm.owner') }}</b>
            @if ($canAssign)
                @include('dashboard::partials.assign-select', ['action' => route('dashboard.requests.assign', $item), 'current' => $item->assigned_to, 'assignable' => $assignable])
            @else
                <span>{{ $item->assignee?->name ?? __('aqdar.crm.unassigned') }}</span>
            @endif
        </div>
        <form method="POST" action="{{ route('dashboard.requests.refresh', $item) }}" style="padding:0 14px 12px">
            @csrf
            <button class="btn ghost sm" type="submit">{{ __('aqdar.requests.refresh') }}</button>
        </form>
        <form method="POST" action="{{ route('dashboard.requests.matches.attach', $item) }}" class="filters" style="padding:0 14px 12px;margin:0">
            @csrf
            <input type="text" name="listing_code" required placeholder="{{ __('aqdar.requests.listing_code') }}" class="btn ghost en" value="{{ old('listing_code') }}">
            <button class="btn sm" type="submit">{{ __('aqdar.requests.attach') }}</button>
        </form>
        <div class="tw">
            <table class="t">
                <tr>
                    <th>{{ __('aqdar.crm.listing') }}</th>
                    <th>{{ __('aqdar.listings.desk.market') }}</th>
                    <th>{{ __('aqdar.listings.price') }}</th>
                    <th>%</th>
                    <th>{{ __('aqdar.requests.sent') }}</th>
                    <th>{{ __('structer.status') }}</th>
                    <th>{{ __('structer.actions') }}</th>
                </tr>
                @forelse ($item->matches as $match)
                    <tr>
                        <td>
                            <b>{{ $match->listing?->localizedTitle() }}</b>
                            <div class="tiny en">{{ $match->listing?->public_code }}</div>
                        </td>
                        <td>{{ $match->listing?->marketLabel() ?? '—' }}</td>
                        <td class="en">{{ $match->listing?->publicAskingPrice() }}</td>
                        <td class="en">{{ $match->score }}%</td>
                        <td class="en">{{ $match->sent_at?->format('Y/m/d') ?? '—' }}</td>
                        <td>{{ $match->buyer_response ? __('aqdar.requests.response.'.$match->buyer_response->value) : '—' }}</td>
                        <td>
                            @if ($match->sent_at === null)
                                <form method="POST" action="{{ route('dashboard.requests.matches.send', [$item, $match]) }}">@csrf<button class="btn ghost sm" type="submit">{{ __('aqdar.requests.send') }}</button></form>
                            @else
                                <form method="POST" action="{{ route('dashboard.requests.matches.response', [$item, $match]) }}">
                                    @csrf
                                    <select name="buyer_response" onchange="this.form.submit()">
                                        <option value="">—</option>
                                        @foreach (MatchBuyerResponse::cases() as $resp)
                                            <option value="{{ $resp->value }}" @selected($match->buyer_response === $resp)>{{ __('aqdar.requests.response.'.$resp->value) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('dashboard.requests.matches.open-lead', [$item, $match]) }}">@csrf<button class="btn sm" type="submit">{{ __('aqdar.requests.open_lead') }}</button></form>
                            @if ($match->sent_at !== null)
                                <form method="POST" action="{{ route('dashboard.requests.convert', $item) }}">
                                    @csrf
                                    <input type="hidden" name="reason" value="{{ __('aqdar.crm.convert_default') }}">
                                    <input type="hidden" name="match_id" value="{{ $match->id }}">
                                    <button class="btn ghost sm" type="submit">@include('dashboard::partials.icon', ['name' => 'swap']){{ __('aqdar.requests.open_case_for_match') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">{{ __('aqdar.requests.empty') }}</td></tr>
                @endforelse
            </table>
        </div>
        <p class="tiny">{{ __('aqdar.requests.privacy') }}</p>
    </div>

    <div class="card" style="margin-top:14px">
        <div class="card-h"><b>{{ __('aqdar.crm.stage') }}</b></div>
        <form method="POST" action="{{ route('dashboard.requests.status', $item) }}" class="toolbar">
            @csrf
            <select name="status">
                @foreach (BuyerRequestStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($item->status === $status)>{{ __('aqdar.requests.status.'.$status->value) }}</option>
                @endforeach
            </select>
            <input type="text" name="reason" required minlength="3" value="{{ __('aqdar.crm.status_default') }}">
            <button class="btn sm" type="submit">{{ __('aqdar.crm.update_status') }}</button>
        </form>
        @if ($item->status?->isOpen())
            <form method="POST" action="{{ route('dashboard.requests.convert', $item) }}" class="toolbar">
                @csrf
                <input type="hidden" name="reason" value="{{ __('aqdar.crm.convert_default') }}">
                <button class="btn sm" type="submit">@include('dashboard::partials.icon', ['name' => 'swap']){{ __('aqdar.requests.convert') }}</button>
            </form>
        @endif
    </div>

    <div class="card" style="margin-top:14px">
        <div class="card-h"><b>{{ __('aqdar.requests.history') }}</b></div>
        <div class="tw">
            <table class="t">
                @forelse ($item->history as $entry)
                    <tr>
                        <td class="en tiny">{{ $entry->created_at?->format('Y/m/d H:i') }}</td>
                        <td>
                            @if ($entry->old_status){{ __('aqdar.requests.status.'.$entry->old_status->value) }} {{ app()->getLocale() === 'ar' ? '←' : '→' }} @endif
                            <b>{{ __('aqdar.requests.status.'.$entry->new_status?->value) }}</b>
                        </td>
                        <td>{{ $entry->reason }}</td>
                        <td>{{ $entry->actor?->name ?? $item->buyer?->name }}</td>
                    </tr>
                @empty
                    <tr><td>{{ __('aqdar.requests.history_empty') }}</td></tr>
                @endforelse
            </table>
        </div>
    </div>
@endsection
