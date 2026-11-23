@extends('dashboard::layouts.app')

@section('title', __('aqdar.support.title'))
@section('screen', 'ADM-07')

@section('content')
    <div class="ptitle">
        <h2>{{ __('aqdar.support.title') }}</h2>
        <div class="acts">
            <a class="btn ghost" href="{{ route('dashboard.leads.index') }}">{{ __('aqdar.menu.adm_07') }}</a>
        </div>
    </div>
    <p class="tiny">{{ __('aqdar.support.desk_note') }}</p>
    <form method="GET" class="filters">
        <select name="status" onchange="this.form.submit()">
            <option value="all" @selected($status === 'all')>{{ __('aqdar.support.all') }}</option>
            <option value="open" @selected($status === 'open')>{{ __('aqdar.support.open') }}</option>
            <option value="closed" @selected($status === 'closed')>{{ __('aqdar.support.closed') }}</option>
        </select>
    </form>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('aqdar.crm.code') }}</th>
                    <th>USR</th>
                    <th>{{ __('aqdar.support.assignee') }}</th>
                    <th>{{ __('aqdar.support.status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-support-rows>
                @forelse ($threads as $thread)
                    <tr data-thread-id="{{ $thread->id }}">
                        <td class="en"><b>{{ $thread->public_code }}</b></td>
                        <td class="en">USR-{{ $thread->user_id }}</td>
                        <td>{{ $thread->assignee?->name ?? '—' }}</td>
                        <td>{{ __('aqdar.support.'.$thread->status?->value) }}</td>
                        <td><a class="btn ghost sm" href="{{ route('dashboard.support.show', $thread) }}">{{ __('aqdar.listings.desk.view') }}</a></td>
                    </tr>
                @empty
                    <tr data-support-empty><td colspan="5">{{ __('aqdar.support.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $threads->links() }}
    </div>
@endsection
