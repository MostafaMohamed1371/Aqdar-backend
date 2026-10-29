@props(['key'])
@php
    $isArabic = app()->getLocale() === 'ar';
    $primary = __($key, locale: $isArabic ? 'ar' : 'en');
    $secondary = __($key, locale: $isArabic ? 'en' : 'ar');
@endphp
{{ $primary }}<small>{{ $secondary }}</small>
