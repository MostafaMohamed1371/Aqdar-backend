@php
    $variant = $variant ?? 'light';
@endphp
<div class="brandmark {{ $variant }}">
    <svg class="logo-svg" viewBox="0 0 64 64" aria-hidden="true">
        <path d="M32 4 60 26v34H4V26z" fill="#14283F"/>
        <path d="M32 4 60 26v34H47V31L32 19z" fill="#087E82"/>
        <path d="M4 27 32 5l4 3L8 30v30H4z" fill="#C5985F"/>
        <text x="27" y="53" text-anchor="middle" font-family="Cairo" font-weight="800" font-size="28" fill="#fff">ع</text>
    </svg>
    <div class="word">
        @if (app()->getLocale() === 'ar')
            عَقدار<small>AQDAR</small>
        @else
            AQDAR<small>عَقدار</small>
        @endif
    </div>
</div>
