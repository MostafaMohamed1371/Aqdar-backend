@php
    $flashItems = [];

    if (session()->has('status')) {
        $flashItems[] = [
            'tone' => 'ok',
            'icon' => 'check',
            'title' => __('aqdar.flash.success_title'),
            'lead' => null,
            'messages' => [(string) session('status')],
        ];
    }

    if (session()->has('warning')) {
        $flashItems[] = [
            'tone' => 'warn',
            'icon' => 'warn',
            'title' => __('aqdar.flash.warning_title'),
            'lead' => __('aqdar.flash.warning_hint'),
            'messages' => [(string) session('warning')],
        ];
    }

    if (session()->has('error')) {
        $flashItems[] = [
            'tone' => 'bad',
            'icon' => 'warn',
            'title' => __('aqdar.flash.error_title'),
            'lead' => __('aqdar.flash.error_hint'),
            'messages' => [(string) session('error')],
        ];
    }

    if ($errors->any()) {
        $uniqueErrors = array_values(array_unique($errors->all()));

        $flashItems[] = [
            'tone' => 'bad',
            'icon' => 'warn',
            'title' => __('aqdar.flash.validation_title'),
            'lead' => trans_choice('aqdar.flash.validation_count', count($uniqueErrors), [
                'count' => count($uniqueErrors),
            ]).' — '.__('aqdar.flash.validation_hint'),
            'messages' => $uniqueErrors,
        ];
    }
@endphp

@foreach ($flashItems as $flash)
    <div
        class="flash flash--{{ $flash['tone'] }}"
        role="{{ $flash['tone'] === 'ok' ? 'status' : 'alert' }}"
        data-flash
    >
        <span class="flash__icon" aria-hidden="true">
            @include('dashboard::partials.icon', ['name' => $flash['icon']])
        </span>
        <div class="flash__body">
            <b class="flash__title">{{ $flash['title'] }}</b>
            @if ($flash['lead'])
                <p class="flash__lead">{{ $flash['lead'] }}</p>
            @endif
            @if (count($flash['messages']) === 1)
                <p class="flash__message">{{ $flash['messages'][0] }}</p>
            @else
                <ul class="flash__list">
                    @foreach ($flash['messages'] as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <button
            type="button"
            class="flash__close"
            data-flash-dismiss
            aria-label="{{ __('aqdar.flash.dismiss') }}"
        >
            @include('dashboard::partials.icon', ['name' => 'x'])
        </button>
    </div>
@endforeach
