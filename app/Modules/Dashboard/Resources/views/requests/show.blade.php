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

    <div class="card">
        <div class="card-h">
            <b>{{ __('aqdar.crm.owner') }}</b>
            @if ($canAssign)
                <form method="POST" action="{{ route('dashboard.requests.assign', $item) }}">
                    @csrf
                    <select name="assigned_to" onchange="this.form.submit()">
                        <option value="">{{ __('aqdar.crm.unassigned') }}</option>
                        @foreach ($assignable as $staff)
                            <option value="{{ $staff->id }}" @selected((int) $item->assigned_to === (int) $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </form>
            @else
                <span>{{ $item->assignee?->name ?? __('aqdar.crm.unassigned') }}</span>
            @endif
        </div>
        <form method="POST" action="{{ route('dashboard.requests.refresh', $item) }}" style="padding:0 14px 12px">
            @csrf
            <button class="btn ghost sm" type="submit">{{ __('aqdar.requests.refresh') }}</button>
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
@endsection
