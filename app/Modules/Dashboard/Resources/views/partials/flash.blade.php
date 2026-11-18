@php
    $flashItems = [];
    $documentBlock = session('document_block');
    $documentMessages = $errors->has('documents') ? $errors->get('documents') : [];

    if (session()->has('status')) {
        $flashItems[] = [
            'tone' => 'ok',
            'icon' => 'check',
            'title' => __('aqdar.flash.success_title'),
            'lead' => null,
            'messages' => [(string) session('status')],
            'chips' => [],
            'action' => null,
        ];
    }

    if (session()->has('warning')) {
        $flashItems[] = [
            'tone' => 'warn',
            'icon' => 'warn',
            'title' => __('aqdar.flash.warning_title'),
            'lead' => __('aqdar.flash.warning_hint'),
            'messages' => [(string) session('warning')],
            'chips' => [],
            'action' => null,
        ];
    }

    if (session()->has('error')) {
        $flashItems[] = [
            'tone' => 'bad',
            'icon' => 'warn',
            'title' => __('aqdar.flash.error_title'),
            'lead' => __('aqdar.flash.error_hint'),
            'messages' => [(string) session('error')],
            'chips' => [],
            'action' => null,
        ];
    }

    if (is_array($documentBlock)) {
        $reason = (string) ($documentBlock['reason'] ?? 'not_approved');
        $flashItems[] = [
            'tone' => 'block',
            'icon' => 'doc',
            'title' => $reason === 'missing'
                ? __('aqdar.listings.documents_missing_title')
                : __('aqdar.listings.documents_block_title'),
            'lead' => $reason === 'missing'
                ? __('aqdar.listings.documents_missing_hint')
                : __('aqdar.listings.documents_block_hint'),
            'messages' => array_values(array_filter([
                $documentBlock['message'] ?? null,
            ])),
            'chips' => array_values(array_filter((array) ($documentBlock['labels'] ?? []))),
            'action' => [
                'url' => (string) ($documentBlock['action_url'] ?? route('dashboard.documents.index', ['status' => 'pending'])),
                'label' => __('aqdar.listings.documents_block_action'),
            ],
        ];
    }

    if ($errors->any()) {
        $uniqueErrors = array_values(array_unique(array_filter(
            $errors->all(),
            static fn (string $message): bool => ! in_array($message, $documentMessages, true),
        )));

        if ($uniqueErrors !== []) {
            $flashItems[] = [
                'tone' => 'bad',
                'icon' => 'warn',
                'title' => __('aqdar.flash.validation_title'),
                'lead' => trans_choice('aqdar.flash.validation_count', count($uniqueErrors), [
                    'count' => count($uniqueErrors),
                ]).' — '.__('aqdar.flash.validation_hint'),
                'messages' => $uniqueErrors,
                'chips' => [],
                'action' => null,
            ];
        }
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
            @elseif (count($flash['messages']) > 1)
                <ul class="flash__list">
                    @foreach ($flash['messages'] as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($flash['chips'] !== [])
                <div class="flash__chips">
                    @foreach ($flash['chips'] as $chip)
                        <span class="flash__chip">{{ $chip }}</span>
                    @endforeach
                </div>
            @endif
            @if ($flash['action'])
                <a class="flash__action" href="{{ $flash['action']['url'] }}">
                    @include('dashboard::partials.icon', ['name' => 'doc'])
                    <span>{{ $flash['action']['label'] }}</span>
                </a>
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
