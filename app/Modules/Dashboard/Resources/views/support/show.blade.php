@extends('dashboard::layouts.app')

@section('title', $thread->public_code)
@section('screen', 'ADM-07')

@section('content')
    <div class="ptitle">
        <h2>{{ $thread->public_code }} · USR-{{ $thread->user_id }}</h2>
        <div class="acts">
            <a class="btn ghost" href="{{ route('dashboard.support.index') }}">{{ __('aqdar.listings.desk.back') }}</a>
            @if ($thread->status?->value === 'open')
                <form method="POST" action="{{ route('dashboard.support.close', $thread) }}">
                    @csrf
                    <button class="btn ghost" type="submit">{{ __('aqdar.support.close') }}</button>
                </form>
            @endif
        </div>
    </div>
    <p class="tiny">{{ __('aqdar.support.desk_note') }}</p>
    <div
        class="cols2e"
        data-support-thread="{{ $thread->id }}"
        data-user-id="{{ $thread->user_id }}"
        data-label-sales="{{ __('aqdar.support.sales') }}"
    >
        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.support.thread') }}</b></div>
            <div data-support-messages>
                @foreach ($thread->messages as $message)
                    <div class="dl" data-message-id="{{ $message->id }}">
                        <span>{{ $message->author_type?->value === 'staff' ? ($message->staff?->name ?? __('aqdar.support.sales')) : 'USR-'.$thread->user_id }}</span>
                        <span>{{ $message->body }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @if ($thread->status?->value === 'open')
            <div class="card">
                <div class="card-h"><b>{{ __('aqdar.support.reply') }}</b></div>
                <form method="POST" action="{{ route('dashboard.support.reply', $thread) }}">
                    @csrf
                    <div class="field">
                        <label for="support-body">{{ __('aqdar.support.reply') }}</label>
                        <div class="inp">
                            <textarea id="support-body" name="body" rows="4" required maxlength="2000">{{ old('body') }}</textarea>
                        </div>
                    </div>
                    @error('body')<p class="tiny" style="color:var(--bad)">{{ $message }}</p>@enderror
                    <button class="btn" type="submit">{{ __('aqdar.support.reply') }}</button>
                </form>
            </div>
        @endif
    </div>
@endsection
