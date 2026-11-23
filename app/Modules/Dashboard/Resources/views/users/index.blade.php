@php
    use App\Core\Identity\ContactMask;
@endphp
@extends('dashboard::layouts.app')

@section('title', __('aqdar.users.title'))
@section('screen', 'ADM-04')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.users.title" /></h2>
        <div class="acts">
            @can('suspend_user')
                <a href="{{ route('dashboard.users.create') }}" class="btn">
                    @include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.users.add') }}
                </a>
            @endcan
        </div>
    </div>

    <div class="kpis">
        <div class="kpi">
            <span class="ic">@include('dashboard::partials.icon', ['name' => 'users'])</span>
            <div>
                <span>{{ __('aqdar.users.total') }}</span>
                <b>{{ number_format($stats['total']) }}</b>
            </div>
        </div>
        <div class="kpi">
            <span class="ic info">@include('dashboard::partials.icon', ['name' => 'user'])</span>
            <div>
                <span>{{ __('aqdar.users.individuals') }}</span>
                <b>{{ number_format($stats['individuals']) }}</b>
            </div>
        </div>
        <div class="kpi">
            <span class="ic gold">@include('dashboard::partials.icon', ['name' => 'building'])</span>
            <div>
                <span>{{ __('aqdar.users.developers') }}</span>
                <b>{{ number_format($stats['developers']) }}</b>
            </div>
        </div>
        <div class="kpi">
            <span class="ic" style="color:var(--violet,#6b5b95)">@include('dashboard::partials.icon', ['name' => 'briefcase'])</span>
            <div>
                <span>{{ __('aqdar.users.brokers') }}</span>
                <b>{{ number_format($stats['brokers']) }}</b>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('dashboard.users.index') }}" class="toolbar">
        <div class="q">
            @include('dashboard::partials.icon', ['name' => 'search'])
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('aqdar.users.search') }}">
        </div>
        <button type="submit" class="btn ghost">{{ __('aqdar.search') }}</button>
    </form>

    <div class="tw">
        <table class="t">
            <tr>
                <th>{{ __('structer.name') }}</th>
                <th>{{ __('aqdar.users.type') }}</th>
                <th>{{ __('structer.email') }}</th>
                <th>{{ __('aqdar.users.phone') }}</th>
                <th>{{ __('aqdar.users.identity') }}</th>
                <th>{{ __('aqdar.users.activity') }}</th>
                <th>{{ __('aqdar.users.account') }}</th>
                <th>{{ __('aqdar.users.actions') }}</th>
            </tr>
            @forelse ($users as $user)
                @php
                    $tone = $user->identity_status->badgeTone();
                    $revealed = $revealedPhones[$user->id] ?? null;
                    $revealedEmail = $revealedEmails[$user->id] ?? null;
                    $duplicateReasons = $duplicates[$user->id] ?? [];
                @endphp
                <tr data-live-row="users" data-live-id="{{ $user->id }}">
                    <td>
                        <div class="who">
                            <span class="av s c{{ ($user->id % 6) + 1 }}">{{ mb_substr($user->name, 0, 1) }}</span>
                            <div><b>{{ $user->name }}</b></div>
                        </div>
                    </td>
                    <td>{{ $user->account_type->labelAr() }}</td>
                    <td class="en">{{ $revealedEmail ?: ContactMask::email($user->email) }}</td>
                    <td>
                        <span class="en">{{ $revealed ?? ContactMask::phone($user->phone) }}</span>
                        @can('reveal_phone')
                            @if (($user->phone || $user->email) && ! $revealed)
                                <form method="POST" action="{{ route('dashboard.users.reveal', $user) }}" style="display:inline">
                                    @csrf
                                    <button class="ib" type="submit" title="{{ __('aqdar.users.reveal_title') }}">
                                        @include('dashboard::partials.icon', ['name' => 'eye'])
                                    </button>
                                </form>
                            @endif
                        @endcan
                    </td>
                    <td><span class="badge {{ $tone }}">{{ $user->identity_status->labelAr() }}</span></td>
                    <td>{{ $user->activityLabel() }}</td>
                    <td>
                        @if ($user->isClosed())
                            <span class="badge bad" data-live-field="status">{{ __('aqdar.users.closed_badge') }}</span>
                        @elseif ($user->isClosePending())
                            <span class="badge gold" data-live-field="status">{{ __('aqdar.users.close_requested_badge') }}</span>
                        @elseif ($user->isSuspended())
                            <span class="badge bad" data-live-field="status">{{ __('aqdar.users.suspended_badge') }}</span>
                        @else
                            <span class="badge ok" data-live-field="status">{{ __('aqdar.users.active_badge') }}</span>
                        @endif
                        @if ($duplicateReasons !== [])
                            <span class="badge warn" title="{{ collect($duplicateReasons)->map(fn ($reason) => __('aqdar.users.duplicate_reasons.'.$reason))->implode(' · ') }}">{{ __('aqdar.users.possible_duplicate') }}</span>
                        @endif
                    </td>
                    <td>
                        @can('suspend_user')
                            @if (! $user->isClosed())
                                <a href="{{ route('dashboard.users.edit', $user) }}" class="btn ghost sm">{{ __('structer.edit') }}</a>
                            @endif
                            <form method="POST" action="{{ route('dashboard.users.destroy', $user) }}" onsubmit="return confirm(@js(__('aqdar.users.confirm_delete')))" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn bad sm">{{ __('structer.delete') }}</button>
                            </form>
                            @if ($user->isClosePending())
                                <form method="POST" action="{{ route('dashboard.users.close.complete', $user) }}" onsubmit="return confirm(@js(__('aqdar.users.confirm_complete_close')))" style="display:inline">
                                    @csrf
                                    <button class="btn sm" type="submit">{{ __('aqdar.users.complete_close') }}</button>
                                </form>
                                <form method="POST" action="{{ route('dashboard.users.close.decline', $user) }}" style="display:inline">
                                    @csrf
                                    <button class="btn ghost sm" type="submit">{{ __('aqdar.users.decline_close') }}</button>
                                </form>
                            @elseif ($user->isSuspended())
                                <form method="POST" action="{{ route('dashboard.users.reactivate', $user) }}" style="display:inline">
                                    @csrf
                                    <button class="btn sm" type="submit">{{ __('aqdar.users.reactivate') }}</button>
                                </form>
                            @elseif (! $user->isClosed())
                                <form method="POST" action="{{ route('dashboard.users.suspend', $user) }}" style="display:inline-flex;gap:6px;align-items:center">
                                    @csrf
                                    <input class="btn ghost sm" name="reason" placeholder="{{ __('aqdar.users.reason') }}" required>
                                    <button class="btn ghost sm" type="submit">{{ __('aqdar.users.suspend') }}</button>
                                </form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">{{ __('structer.no_users_yet') }}</td>
                </tr>
            @endforelse
        </table>
    </div>

    {{ $users->links() }}

    <p class="tiny">@include('dashboard::partials.icon', ['name' => 'lock']) {{ __('aqdar.users.mask_note') }} {{ __('aqdar.users.closure_note') }}</p>
@endsection
