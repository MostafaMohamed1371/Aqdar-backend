<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>{{ $listing['title'] }} · {{ $code }}</title>
    @include('dashboard::partials.favicon')
    <style>
        body{margin:0;font-family:Cairo,system-ui,sans-serif;background:#f6f1e8;color:#14283f}
        .wrap{max-width:720px;margin:24px auto;background:#fff;border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(20,40,63,.06)}
        .en{font-family:Inter,system-ui,sans-serif}
        .cta{display:inline-block;margin-top:16px;background:#087e82;color:#fff;text-decoration:none;padding:10px 16px;border-radius:10px}
        .muted{color:#6b7280;font-size:13px}
        img{max-width:100%;border-radius:12px}
    </style>
</head>
<body>
    <main class="wrap" data-screen="APP-40">
        <p class="en muted">{{ $code }}</p>
        <h1>{{ $listing['title'] }}</h1>
        @if (! empty($listing['photos'][0]['url']))
            <p><img src="{{ $listing['photos'][0]['url'] }}" alt=""></p>
        @endif
        @if (! empty($listing['asking_price']))
            <p><b class="en">{{ number_format((float) $listing['asking_price']) }} {{ $listing['currency'] }}</b></p>
        @endif
        <p>{{ $listing['description'] }}</p>
        <p class="muted">{{ data_get($listing, 'governorate.name') }} @if(data_get($listing, 'area.name')) · {{ data_get($listing, 'area.name') }} @endif</p>
        @if (! empty($listing['phase']) || data_get($listing, 'parking') !== null || ! empty($listing['offer_expires_at']))
            <p class="muted">
                @if (! empty($listing['phase'])) {{ __('aqdar.share.phase') }}: {{ $listing['phase'] }} @endif
                @if (data_get($listing, 'parking') !== null) · {{ __('aqdar.share.parking') }}: <span class="en">{{ $listing['parking'] }}</span> @endif
                @if (! empty($listing['offer_expires_at'])) · {{ __('aqdar.share.offer_expires') }}: <span class="en">{{ \Illuminate\Support\Str::of((string) $listing['offer_expires_at'])->before('T') }}</span> @endif
            </p>
        @endif
        <p>
            {{ __('aqdar.share.sales_cta') }}
            <b class="en">{{ data_get($listing, 'sales_contact.phone') }}</b>
        </p>
        <a class="cta" href="{{ url('/dashboard') }}">{{ __('aqdar.share.open_app') }}</a>
    </main>
</body>
</html>
