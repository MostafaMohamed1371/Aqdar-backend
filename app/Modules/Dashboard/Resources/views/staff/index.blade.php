@extends('dashboard::layouts.app')

@section('title', __('aqdar.staff.title'))
@section('screen', 'ADM-06')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.staff.title" /></h2>
    </div>

    <div class="kpis">
        <div class="kpi">
            <span class="ic">@include('dashboard::partials.icon', ['name' => 'users'])</span>
            <div>
                <span>{{ __('aqdar.staff.total') }}</span>
                <b>{{ number_format($stats['total']) }}</b>
            </div>
        </div>
        <div class="kpi">
            <span class="ic gold">@include('dashboard::partials.icon', ['name' => 'building'])</span>
            <div>
                <span>{{ __('aqdar.staff.branches') }}</span>
                <b>{{ number_format($stats['branches']) }}</b>
            </div>
        </div>
        <div class="kpi">
            <span class="ic info">@include('dashboard::partials.icon', ['name' => 'briefcase'])</span>
            <div>
                <span>{{ __('aqdar.staff.sales_team') }}</span>
                <b>{{ number_format($stats['sales']) }}</b>
            </div>
        </div>
        <div class="kpi">
            <span class="ic violet">@include('dashboard::partials.icon', ['name' => 'shield'])</span>
            <div>
                <span>{{ __('aqdar.staff.admin_ops') }}</span>
                <b>{{ number_format($stats['admin']) }}</b>
            </div>
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" action="{{ route('dashboard.staff.index') }}" class="q" style="flex:1">
            @include('dashboard::partials.icon', ['name' => 'search'])
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('aqdar.staff.search') }}">
        </form>
        <a href="{{ route('dashboard.staff.create') }}" class="btn">
            @include('dashboard::partials.icon', ['name' => 'plus']){{ __('aqdar.staff.add') }}
        </a>
    </div>

    <div class="cols2e">
        <div class="tw">
            <table class="t">
                <tr>
                    <th>{{ __('structer.name') }}</th>
                    <th>{{ __('structer.email') }}</th>
                    <th>{{ __('aqdar.staff.role') }}</th>
                    <th>{{ __('aqdar.staff.branch') }}</th>
                    <th>{{ __('aqdar.users.account') }}</th>
                    <th>{{ __('aqdar.users.actions') }}</th>
                </tr>
                @forelse ($staffUsers as $member)
                    <tr>
                        <td>
                            <div class="who">
                                @include('dashboard::partials.staff-avatar', ['staff' => $member])
                                <div><b>{{ $member->name }}</b></div>
                            </div>
                        </td>
                        <td class="en">{{ $member->email }}</td>
                        <td>{{ $member->role?->name_ar }}</td>
                        <td>{{ $member->branch ?? '—' }}</td>
                        <td>
                            @if ($member->is_active)
                                <span class="badge ok">{{ __('aqdar.users.active_badge') }}</span>
                            @else
                                <span class="badge bad">{{ __('aqdar.staff.access_revoked') }}</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('dashboard.staff.edit', $member) }}" class="btn ghost sm">{{ __('structer.edit') }}</a>
                            <form method="POST" action="{{ route('dashboard.staff.destroy', $member) }}" onsubmit="return confirm(@js(__('aqdar.staff.confirm_delete')))" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn bad sm">{{ __('structer.delete') }}</button>
                            </form>
                            @if ($member->is_active)
                                <details style="display:inline-block">
                                    <summary class="btn ghost sm">{{ __('aqdar.staff.sign_out_everywhere') }}</summary>
                                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.staff.sign-out', $member), 'label' => __('aqdar.staff.sign_out_everywhere'), 'tone' => 'ghost'])
                                </details>
                                <details style="display:inline-block">
                                    <summary class="btn ghost sm">{{ __('aqdar.staff.revoke') }}</summary>
                                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.staff.deactivate', $member), 'label' => __('aqdar.staff.revoke'), 'tone' => 'bad'])
                                </details>
                            @else
                                <form method="POST" action="{{ route('dashboard.staff.activate', $member) }}" style="display:inline">
                                    @csrf
                                    <button class="btn sm" type="submit">{{ __('aqdar.staff.restore') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">{{ __('aqdar.staff.empty') }}</td>
                    </tr>
                @endforelse
            </table>
            {{ $staffUsers->links() }}
        </div>

        <div class="card" style="margin:0">
            <div class="card-h">
                <b>{{ __('aqdar.staff.matrix') }}</b>
                <span class="tiny">{{ __('aqdar.staff.matrix_note') }}</span>
            </div>
            <table class="t perm">
                <tr>
                    <th>{{ __('aqdar.staff.permission') }}</th>
                    @foreach ($staffRoles as $staffRole)
                        <th>{{ $staffRole->labelAr() }}</th>
                    @endforeach
                </tr>
                @foreach ($permissions as $permission)
                    <tr>
                        <td>{{ $permission->labelAr() }}</td>
                        @foreach ($staffRoles as $staffRole)
                            <td>
                                @if ($matrix[$permission->value][$staffRole->value] ?? false)
                                    <span style="color:var(--ok)">@include('dashboard::partials.icon', ['name' => 'check'])</span>
                                @else
                                    <span style="color:#c9ced6">@include('dashboard::partials.icon', ['name' => 'x'])</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
@endsection
