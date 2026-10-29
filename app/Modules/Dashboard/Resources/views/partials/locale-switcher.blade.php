@php
    $locales = config('structer.available_locales', ['en']);
    $labels = config('structer.locale_labels', []);
    $current = app()->getLocale();
    $currentLabel = $labels[$current] ?? strtoupper($current);
@endphp

<div class="relative locale-switcher" data-locale-dropdown>
    <button
        type="button"
        class="btn ghost sm"
        data-locale-toggle
        aria-expanded="false"
        aria-haspopup="listbox"
    >
        <svg class="i s" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m14.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5a17.92 17.92 0 01-8.716-2.247m0 0A8.966 8.966 0 013 12c0-1.97.633-3.794 1.716-5.253" />
        </svg>
        <span>{{ $currentLabel }}</span>
        <svg data-locale-chevron class="i s" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div
        data-locale-menu
        role="listbox"
        class="locale-menu hidden"
    >
        @foreach ($locales as $locale)
            <a
                href="{{ route('locale.switch', $locale) }}"
                role="option"
                @class(['on' => $current === $locale])
            >
                {{ $labels[$locale] ?? strtoupper($locale) }}
            </a>
        @endforeach
    </div>
</div>
