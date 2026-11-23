@php
    use App\Core\Enums\CommissionBasis;
    use App\Core\Enums\CommissionPayer;
    use App\Core\Enums\CommissionStatus;
@endphp
@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_10'))
@section('screen', 'ADM-10')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_10" /></h2>
        <div class="acts">
            @if ($canExport ?? false)
                <a class="btn ghost" href="{{ route('dashboard.commissions.export', array_filter(['status' => $status !== 'all' ? $status : null, 'q' => $search ?: null, 'month' => $month ?: null])) }}">@include('dashboard::partials.icon', ['name' => 'download']){{ __('aqdar.commissions.csv') }}</a>
            @endif
            <form method="GET" action="{{ route('dashboard.commissions.index') }}" style="display:inline">
                @if ($status !== 'all')
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                @if ($search !== '')
                    <input type="hidden" name="q" value="{{ $search }}">
                @endif
                <select class="btn ghost" name="month" onchange="this.form.submit()">
                    <option value="">{{ __('aqdar.commissions.all_months') }}</option>
                    @foreach ($monthOptions as $key => $label)
                        <option value="{{ $key }}" @selected($month === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
            @if ($canEdit ?? false)
                <a class="btn" href="#add-commission">@include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.commissions.add') }}</a>
            @endif
        </div>
    </div>
    <p class="tiny">{{ ($canEdit ?? false) ? __('aqdar.commissions.manual_note') : (($canApprove ?? false) ? __('aqdar.commissions.approval_note') : __('aqdar.commissions.own_only_note')) }}</p>

    <div class="kpis">
        @foreach ([
            ['cash', '', 'total_amount', 'aqdar.commissions.kpi.total'],
            ['clock', 'gold', 'due_amount', 'aqdar.commissions.kpi.due'],
            ['swap', 'info', 'deal_count', 'aqdar.commissions.kpi.deals'],
            ['percent', 'ok', 'average_rate', 'aqdar.commissions.kpi.average'],
        ] as [$icon, $tone, $key, $label])
            <div class="kpi">
                <span class="ic {{ $tone }}">@include('dashboard::partials.icon', ['name' => $icon])</span>
                <div>
                    <span>{{ __($label) }}</span>
                    <b class="en">{{ $key === 'average_rate' ? number_format((float) ($counts[$key] ?? 0), 1).'%' : number_format((float) ($counts[$key] ?? 0)) }}</b>
                </div>
            </div>
        @endforeach
    </div>

    @php
        $chartMonths = $charts['months'] ?? [];
        $chartMax = max(array_merge(array_column($chartMonths, 'revenue'), array_column($chartMonths, 'commission'), [0])) * 1.15;
        $chartMax = $chartMax > 0 ? $chartMax : 1;
        $chartW = 560;
        $chartH = 220;
        $chartPad = 30;
        $chartN = max(count($chartMonths), 1);
        $chartM = 2;
        $chartGw = ($chartW - $chartPad - 10) / $chartN;
        $chartBw = min(26, ($chartGw - 14) / $chartM);
        $donutOff = 25;
    @endphp
    <div class="cols2">
        <div class="card" data-chart="monthly">
            <div class="card-h">
                <b>{{ __('aqdar.commissions.charts.monthly') }}</b>
                <div class="legend">
                    <span><i style="background:#087e82"></i>{{ __('aqdar.commissions.charts.revenue') }}</span>
                    <span><i style="background:#c5985f"></i>{{ __('aqdar.commissions.charts.commissions') }}</span>
                </div>
            </div>
            <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" width="100%" style="direction:ltr;display:block">
                @for ($grid = 0; $grid <= 4; $grid++)
                    @php $yy = 14 + ($chartH - 40) * $grid / 4; @endphp
                    <line x1="{{ $chartPad }}" x2="{{ $chartW - 10 }}" y1="{{ $yy }}" y2="{{ $yy }}" stroke="#eef0ee"/>
                @endfor
                @foreach ($chartMonths as $i => $row)
                    @php
                        $gx = $chartPad + $chartGw * $i + ($chartGw - $chartBw * $chartM - 4 * ($chartM - 1)) / 2;
                        $hRev = ($chartH - 40) * ((float) $row['revenue']) / $chartMax;
                        $hCom = ($chartH - 40) * ((float) $row['commission']) / $chartMax;
                    @endphp
                    <rect x="{{ $gx }}" y="{{ $chartH - 26 - $hRev }}" width="{{ $chartBw }}" height="{{ $hRev }}" rx="5" fill="#087e82"/>
                    <rect x="{{ $gx + $chartBw + 4 }}" y="{{ $chartH - 26 - $hCom }}" width="{{ $chartBw }}" height="{{ $hCom }}" rx="5" fill="#c5985f"/>
                    <text class="chart-muted" x="{{ $chartPad + $chartGw * $i + $chartGw / 2 }}" y="{{ $chartH - 8 }}" text-anchor="middle" font-size="10.5">{{ $row['label'] }}</text>
                @endforeach
            </svg>
        </div>
        <div class="card" data-chart="by-type">
            <div class="card-h"><b>{{ __('aqdar.commissions.charts.by_type') }}</b></div>
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;justify-content:center">
                <svg viewBox="0 0 42 42" width="140" height="140" style="flex-shrink:0">
                    @foreach (($charts['types'] ?? []) as $seg)
                        <circle r="15.915" cx="21" cy="21" fill="none" stroke="{{ $seg['color'] }}" stroke-width="6" stroke-dasharray="{{ $seg['percent'] }} {{ 100 - $seg['percent'] }}" stroke-dashoffset="{{ $donutOff }}"/>
                        @php $donutOff -= $seg['percent']; @endphp
                    @endforeach
                    <text class="chart-fg" x="21" y="21.5" text-anchor="middle" font-size="6.2" font-weight="800">{{ $charts['revenue_label'] ?? '0' }}</text>
                    <text class="chart-muted" x="21" y="27" text-anchor="middle" font-size="3.2">{{ __('aqdar.commissions.charts.revenue') }}</text>
                </svg>
                <div style="flex:1;min-width:130px">
                    @foreach (($charts['types'] ?? []) as $seg)
                        <div class="dl" style="white-space:nowrap">
                            <span><i style="display:inline-block;width:10px;height:10px;border-radius:3px;background:{{ $seg['color'] }};margin-left:6px"></i>{{ $seg['label'] }}</span>
                            <b class="en">{{ $seg['percent'] }}%</b>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('dashboard.commissions.index') }}" class="filters">
        @if ($month !== '')
            <input type="hidden" name="month" value="{{ $month }}">
        @endif
        <div class="q">
            @include('dashboard::partials.icon', ['name' => 'search'])
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('aqdar.commissions.search') }}">
        </div>
        <select name="status">
            <option value="all">{{ __('aqdar.commissions.all_statuses') }}</option>
            @foreach (CommissionStatus::cases() as $st)
                <option value="{{ $st->value }}" @selected($status === $st->value)>{{ __('aqdar.commissions.status.'.$st->value) }}</option>
            @endforeach
        </select>
        @include('dashboard::partials.date-range')
        <button class="btn ghost" type="submit">{{ __('aqdar.search') }}</button>
    </form>

    <div class="card-h"><b>{{ __('aqdar.commissions.recent') }}</b></div>
    <div class="tw">
        <table class="t">
            <tr>
                @include('dashboard::partials.sort-th', ['key' => 'date', 'label' => __('aqdar.commissions.date')])
                <th>{{ __('aqdar.crm.code') }}</th>
                <th>{{ __('aqdar.commissions.payer') }}</th>
                <th>{{ __('aqdar.crm.listing') }}</th>
                @include('dashboard::partials.sort-th', ['key' => 'sale_value', 'label' => __('aqdar.commissions.sale_value')])
                <th>{{ __('aqdar.commissions.basis') }}</th>
                @include('dashboard::partials.sort-th', ['key' => 'amount', 'label' => __('aqdar.commissions.amount')])
                <th>{{ __('aqdar.commissions.invoice') }}</th>
                @include('dashboard::partials.sort-th', ['key' => 'status', 'label' => __('structer.status')])
                <th></th>
            </tr>
            @forelse ($commissions as $item)
                <tr data-live-row="commissions" data-live-id="{{ $item->id }}">
                    <td class="en">{{ $item->created_at?->format('Y/m/d') }}</td>
                    <td>
                        @if ($item->transferCase && (auth()->user()?->can('manage_crm') || auth()->user()?->can('advance_finance_stages')))
                            <a class="en" href="{{ route('dashboard.cases.show', $item->transferCase) }}"><b>{{ $item->transferCase->public_code }}</b></a>
                        @elseif ($item->transferCase)
                            <b class="en">{{ $item->transferCase->public_code }}</b>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ __('aqdar.commissions.party.'.$item->payer_party?->value) }}</td>
                    <td>{{ $item->listing?->deskSummary() ?? '—' }}</td>
                    <td class="en">{{ $item->sale_value !== null ? number_format((float) $item->sale_value) : '—' }}</td>
                    <td>{{ $item->basis === CommissionBasis::Percent ? __('aqdar.commissions.percent_of', ['rate' => $item->rate]) : __('aqdar.commissions.fixed') }}</td>
                    <td><b class="en">{{ number_format((float) $item->amount) }}</b></td>
                    <td class="en">{{ $item->invoice_reference ?? '—' }}</td>
                    <td>
                        <span class="badge {{ $item->status === CommissionStatus::Paid ? 'ok' : ($item->status === CommissionStatus::Due ? 'warn' : (in_array($item->status, [CommissionStatus::Waived, CommissionStatus::Reversed], true) ? 'grey' : 'gold')) }}" data-live-field="status">{{ __('aqdar.commissions.status.'.$item->status?->value) }}</span>
                        @unless ($item->isApproved())
                            <span class="badge warn">{{ __('aqdar.commissions.pending') }}</span>
                        @endunless
                    </td>
                    <td>
                        @if ($canEdit ?? false)
                            <details>
                                <summary class="icon-btn" title="{{ __('structer.edit') }}" style="list-style:none;cursor:pointer">@include('dashboard::partials.icon', ['name' => 'edit'])</summary>
                                <form method="POST" action="{{ route('dashboard.commissions.update', $item) }}" class="toolbar" style="flex-wrap:wrap;margin-top:6px">
                                    @csrf
                                    @method('PUT')
                                    <select name="status">
                                        @foreach (CommissionStatus::cases() as $st)
                                            <option value="{{ $st->value }}" @selected($item->status === $st)>{{ __('aqdar.commissions.status.'.$st->value) }}</option>
                                        @endforeach
                                    </select>
                                    <input class="en" name="invoice_reference" value="{{ $item->invoice_reference }}" placeholder="{{ __('aqdar.commissions.invoice') }}">
                                    <input name="reason" required minlength="3" placeholder="{{ __('aqdar.commissions.reason') }}">
                                    <button class="btn ghost sm" type="submit">{{ __('structer.save') }}</button>
                                </form>
                            </details>
                        @elseif (($canApprove ?? false) && ! $item->isApproved())
                            <form method="POST" action="{{ route('dashboard.commissions.approve', $item) }}" class="toolbar">
                                @csrf
                                <input name="reason" minlength="3" placeholder="{{ __('aqdar.commissions.reason') }}">
                                <button class="btn sm" type="submit">{{ __('aqdar.commissions.approve') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10">{{ __('aqdar.commissions.empty') }}</td></tr>
            @endforelse
        </table>
    </div>
    {{ $commissions->links() }}

    @if ($latestChange)
        <p class="tiny">
            @if ($latestChange->old_status)
                {{ __('aqdar.commissions.last_change', [
                    'staff' => $latestChange->actor?->name ?? '—',
                    'case' => $latestChange->commission?->transferCase?->public_code ?? '—',
                    'from' => __('aqdar.commissions.status.'.$latestChange->old_status->value),
                    'to' => __('aqdar.commissions.status.'.$latestChange->new_status->value),
                    'reason' => $latestChange->reason,
                    'date' => optional($latestChange->created_at)->format('Y/m/d'),
                ]) }}
            @else
                {{ __('aqdar.commissions.last_change_opened', [
                    'staff' => $latestChange->actor?->name ?? '—',
                    'case' => $latestChange->commission?->transferCase?->public_code ?? '—',
                    'reason' => $latestChange->reason,
                    'date' => optional($latestChange->created_at)->format('Y/m/d'),
                ]) }}
            @endif
        </p>
    @endif

    @if ($canEdit ?? false)
    <div id="add-commission" class="card" style="margin-top:16px">
        <div class="card-h"><b>{{ __('aqdar.commissions.add') }}</b><span class="tiny">{{ __('aqdar.commissions.default_rate', ['rate' => $defaultRate]) }}</span></div>
        @if ($closedCases->isEmpty())
            <p class="tiny" style="padding:12px 14px">{{ __('aqdar.commissions.no_closed_cases') }}</p>
        @else
            <form method="POST" action="{{ route('dashboard.commissions.store') }}" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:14px">
                @csrf
                <label class="tiny">{{ __('aqdar.commissions.closed_case') }}
                    <select name="case_id" required>
                        <option value="">{{ __('aqdar.commissions.choose_case') }}</option>
                        @foreach ($closedCases as $case)
                            <option value="{{ $case->id }}" @selected((string) old('case_id') === (string) $case->id)>{{ $case->public_code }} · {{ $case->listing?->deskSummary() ?? '—' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="tiny">{{ __('aqdar.commissions.payer') }}
                    <select name="payer_party" required>
                        @foreach (CommissionPayer::cases() as $party)
                            <option value="{{ $party->value }}">{{ __('aqdar.commissions.party.'.$party->value) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="tiny">{{ __('aqdar.commissions.basis') }}
                    <select name="basis" required>
                        @foreach (CommissionBasis::cases() as $basis)
                            <option value="{{ $basis->value }}">{{ __('aqdar.commissions.basis_option.'.$basis->value) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="tiny">{{ __('aqdar.commissions.rate') }}
                    <input type="number" name="rate" step="0.001" min="0" value="{{ old('rate', $defaultRate) }}">
                </label>
                <label class="tiny">{{ __('aqdar.commissions.sale_value') }}
                    <input type="number" name="sale_value" step="0.01" min="0" value="{{ old('sale_value') }}">
                </label>
                <label class="tiny">{{ __('aqdar.commissions.amount') }}
                    <input type="number" name="amount" step="0.01" min="0" value="{{ old('amount') }}">
                </label>
                <label class="tiny">{{ __('structer.status') }}
                    <select name="status" required>
                        @foreach (CommissionStatus::cases() as $st)
                            <option value="{{ $st->value }}" @selected($st === CommissionStatus::Earned)>{{ __('aqdar.commissions.status.'.$st->value) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="tiny">{{ __('aqdar.commissions.invoice') }}
                    <input class="en" name="invoice_reference" value="{{ old('invoice_reference') }}">
                </label>
                <label class="tiny" style="grid-column:1 / -1">{{ __('aqdar.commissions.reason') }}
                    <input name="reason" value="{{ old('reason') }}">
                </label>
                <div style="grid-column:1 / -1">
                    <button class="btn" type="submit">@include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.commissions.add') }}</button>
                </div>
            </form>
        @endif
    </div>
    @endif
@endsection
