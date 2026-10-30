@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_14'))
@section('screen', 'ADM-14')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_14" /></h2>
        <div class="acts">
            <a class="btn ghost" href="{{ route('dashboard.audit.export', $filters) }}">@include('dashboard::partials.icon', ['name' => 'download']){{ __('aqdar.audit.export') }}</a>
        </div>
    </div>

    <form method="GET" action="{{ route('dashboard.audit.index') }}" class="filters">
        <div class="q">
            @include('dashboard::partials.icon', ['name' => 'search'])
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('aqdar.audit.search') }}">
        </div>
        <select name="kind">
            <option value="all" @selected($filters['kind'] === 'all')>{{ __('aqdar.audit.kind_all') }}</option>
            <option value="audit" @selected($filters['kind'] === 'audit')>{{ __('aqdar.audit.kind_audit') }}</option>
            <option value="access" @selected($filters['kind'] === 'access')>{{ __('aqdar.audit.kind_access') }}</option>
        </select>
        <input type="text" name="action" value="{{ $filters['action'] === 'all' ? '' : $filters['action'] }}" placeholder="{{ __('aqdar.audit.action') }}" class="en">
        <select name="actor_id">
            <option value="">{{ __('aqdar.audit.actor_all') }}</option>
            @foreach ($actors as $actor)
                <option value="{{ $actor->id }}" @selected((string) $filters['actor_id'] === (string) $actor->id)>{{ $actor->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] }}" class="btn ghost en">
        <input type="date" name="to" value="{{ $filters['to'] }}" class="btn ghost en">
        <button type="submit" class="btn ghost">{{ __('aqdar.reports.apply') }}</button>
    </form>

    <div class="tw">
        <table class="t">
            <tr>
                <th>{{ __('aqdar.audit.time') }}</th>
                <th>{{ __('aqdar.audit.actor') }}</th>
                <th>{{ __('aqdar.audit.event') }}</th>
                <th>{{ __('aqdar.audit.subject') }}</th>
                <th class="en">IP</th>
            </tr>
            @forelse ($rows as $row)
                <tr>
                    <td class="en">{{ optional($row->created_at)?->toDateTimeString() }}</td>
                    <td>{{ $row->actor?->name ?? $row->actor_type }}</td>
                    <td><span class="badge">{{ \App\Modules\Dashboard\Services\AuditSearchService::actionLabel($row->action) }}</span></td>
                    <td class="en" style="max-width:360px;overflow-wrap:anywhere">{{ \App\Modules\Dashboard\Services\AuditSearchService::details($row) }}</td>
                    <td class="en">{{ $row->ip_address }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5"><p class="empty-note">{{ __('aqdar.audit.empty') }}</p></td>
                </tr>
            @endforelse
        </table>
    </div>
    {{ $rows->links() }}
    <p class="tiny">{{ __('aqdar.audit.immutable') }}</p>
@endsection
