@extends('dashboard::layouts.app')

@section('title', $thread?->public_code ?? __('aqdar.support.title'))
@section('screen', 'ADM-07')

@section('content')
    @php
        $activeId = $thread?->id;
        $pollStatus = $status === 'all' ? 'open' : $status;
    @endphp

    <div class="ptitle support-ptitle">
        <div>
            <h2>{{ __('aqdar.support.title') }}</h2>
            <p class="tiny">{{ __('aqdar.support.desk_note') }}</p>
        </div>
        <div class="acts">
            <a class="btn ghost" href="{{ route('dashboard.leads.index') }}">{{ __('aqdar.menu.adm_07') }}</a>
        </div>
    </div>

    <div class="support-desk" data-support-desk>
        <aside class="support-sidebar card">
            <form method="GET" action="{{ route('dashboard.support.index') }}" class="support-filters">
                <div class="support-tabs">
                    @foreach (['open', 'all', 'closed'] as $tab)
                        <a
                            class="support-tab {{ $status === $tab ? 'is-on' : '' }}"
                            href="{{ $activeId
                                ? route('dashboard.support.show', array_filter(['supportThread' => $activeId, 'status' => $tab, 'q' => $search ?: null]))
                                : route('dashboard.support.index', array_filter(['status' => $tab, 'q' => $search ?: null])) }}"
                        >
                            {{ __('aqdar.support.'.$tab) }}
                            <span class="en">{{ number_format($counts[$tab] ?? 0) }}</span>
                        </a>
                    @endforeach
                </div>
                <div class="support-search">
                    <input
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="{{ __('aqdar.support.search_placeholder') }}"
                        autocomplete="off"
                    >
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button class="btn sm" type="submit">{{ __('aqdar.support.search') }}</button>
                </div>
            </form>

            <div
                class="support-thread-list"
                data-support-rows
                data-support-poll-index="{{ route('dashboard.support.poll-index', array_filter(['status' => $pollStatus, 'q' => $search ?: null])) }}"
            >
                    @forelse ($threads as $item)
                    @php
                        $latest = $item->latestMessage;
                        $isActive = $activeId === $item->id;
                    @endphp
                    <a
                        class="support-thread-item {{ $isActive ? 'is-active' : '' }}"
                        data-thread-id="{{ $item->id }}"
                        href="{{ route('dashboard.support.show', ['supportThread' => $item, 'status' => $status, 'q' => $search ?: null]) }}"
                    >
                        <div class="support-thread-avatar en">{{ strtoupper(mb_substr($item->user?->name ?? 'U', 0, 1)) }}</div>
                        <div class="support-thread-meta">
                            <div class="support-thread-top">
                                <b>{{ $item->user?->name ?? 'USR-'.$item->user_id }}</b>
                                <span class="tiny en">{{ $latest?->created_at?->format('H:i') ?? $item->updated_at?->format('Y-m-d') }}</span>
                            </div>
                            <div class="support-thread-sub">
                                <span class="en">{{ $item->public_code }}</span>
                                <span class="badge {{ $item->status?->value === 'open' ? 'ok' : 'grey' }}">{{ __('aqdar.support.'.$item->status?->value) }}</span>
                            </div>
                            <p class="support-thread-preview">{{ $latest?->body ?? __('aqdar.support.no_messages') }}</p>
                        </div>
                    </a>
                @empty
                    <div class="support-empty" data-support-empty>
                        <p>{{ __('aqdar.support.empty') }}</p>
                    </div>
                @endforelse
            </div>

            <div class="support-pager">
                {{ $threads->links() }}
            </div>
        </aside>

        <section class="support-pane card">
            @if ($thread)
                <header class="support-pane-head">
                    <div>
                        <h3>{{ $thread->user?->name ?? 'USR-'.$thread->user_id }}</h3>
                        <p class="tiny">
                            <span class="en">{{ $thread->public_code }}</span>
                            · USR-{{ $thread->user_id }}
                            · {{ __('aqdar.support.assignee') }}: {{ $thread->assignee?->name ?? '—' }}
                        </p>
                    </div>
                    <div class="acts">
                        <span class="badge {{ $thread->status?->value === 'open' ? 'ok' : 'grey' }}">{{ __('aqdar.support.'.$thread->status?->value) }}</span>
                        @if ($thread->status?->value === 'open')
                            <form method="POST" action="{{ route('dashboard.support.close', $thread) }}">
                                @csrf
                                <button class="btn ghost sm" type="submit">{{ __('aqdar.support.close') }}</button>
                            </form>
                        @endif
                    </div>
                </header>

                <div
                    class="support-chat"
                    data-support-thread="{{ $thread->id }}"
                    data-user-id="{{ $thread->user_id }}"
                    data-label-sales="{{ __('aqdar.support.sales') }}"
                    data-label-user="{{ $thread->user?->name ?? 'USR-'.$thread->user_id }}"
                    data-support-poll="{{ route('dashboard.support.poll', $thread) }}"
                >
                    <div class="support-messages" data-support-messages>
                        @foreach ($thread->messages as $message)
                            @php $fromSales = $message->author_type?->value === 'staff'; @endphp
                            <div
                                class="support-bubble {{ $fromSales ? 'is-sales' : 'is-user' }}"
                                data-message-id="{{ $message->id }}"
                                data-author="{{ $fromSales ? 'sales' : 'user' }}"
                            >
                                <div class="support-bubble-meta">
                                    <b>{{ $fromSales ? ($message->staff?->name ?? __('aqdar.support.sales')) : ($thread->user?->name ?? 'USR-'.$thread->user_id) }}</b>
                                    <span class="tiny en">{{ $message->created_at?->format('Y-m-d H:i') }}</span>
                                </div>
                                <div class="support-bubble-body">{{ $message->body }}</div>
                            </div>
                        @endforeach
                    </div>

                    @if ($thread->status?->value === 'open')
                        <form class="support-composer" method="POST" action="{{ route('dashboard.support.reply', $thread) }}">
                            @csrf
                            <textarea name="body" rows="2" required maxlength="2000" placeholder="{{ __('aqdar.support.reply_placeholder') }}">{{ old('body') }}</textarea>
                            <button class="btn" type="submit">{{ __('aqdar.support.reply') }}</button>
                        </form>
                        @error('body')
                            <p class="tiny" style="color:var(--bad);padding:0 14px 12px">{{ $message }}</p>
                        @enderror
                    @else
                        <div class="support-closed-note">{{ __('aqdar.support.closed_hint') }}</div>
                    @endif
                </div>
            @else
                <div class="support-pane-empty">
                    <div class="support-pane-empty-card">
                        <b>{{ __('aqdar.support.pick_title') }}</b>
                        <p class="tiny">{{ __('aqdar.support.pick_hint') }}</p>
                    </div>
                </div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    @include('dashboard::partials.support-poll')
@endpush
