@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_03').' · '.$listing->public_code)
@section('screen', 'ADM-03')

@section('content')
    <div class="ptitle">
        <h2>{{ __('aqdar.listings.docs.checklist') }} · {{ $listing->public_code }}</h2>
        <div class="acts">
            <a class="btn ghost" href="{{ route('dashboard.documents.index') }}">{{ __('aqdar.listings.desk.back') }}</a>
            @can('approve_listing')
                <a class="btn ghost" href="{{ route('dashboard.listings.show', $listing) }}">{{ __('aqdar.menu.adm_02') }}</a>
            @endcan
        </div>
    </div>
    <p class="tiny">{{ __('aqdar.listings.docs.signed_note') }}</p>

    <div class="cols2e">
        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.listings.docs.checklist') }}</b></div>
            @foreach (\App\Core\Enums\DocumentType::cases() as $type)
                @php
                    $needed = $type->isRequiredFor($listing->segment);
                    $versions = $history[$type->value] ?? collect();
                    $doc = $latest[$type->value] ?? $versions->first();
                @endphp
                @continue(! $needed && $doc === null)
                <div class="dl" style="padding:10px 14px;border-bottom:1px solid var(--line);align-items:flex-start">
                    <span>
                        {{ __('aqdar.listings.doc_type.'.$type->value) }}
                        @if ($needed)
                            <span class="badge">{{ __('aqdar.listings.docs.required') }}</span>
                        @endif
                        @if ($doc)
                            <span class="tiny en">v{{ $doc->version }}</span>
                            <span class="badge">{{ __('aqdar.listings.doc_status.'.$doc->review_status->value) }}</span>
                        @else
                            <span class="tiny">{{ __('aqdar.listings.docs.missing') }}</span>
                        @endif
                        @if ($versions->count() > 1)
                            <div class="tiny" style="margin-top:6px">
                                {{ __('aqdar.listings.docs.versions') }}:
                                @foreach ($versions as $version)
                                    <a class="en" href="{{ URL::temporarySignedRoute('dashboard.documents.download', now()->addMinutes(config('aqdar.files.signed_url_minutes', 10)), ['document' => $version]) }}">v{{ $version->version }}</a>
                                    @unless ($loop->last) · @endunless
                                @endforeach
                            </div>
                        @endif
                    </span>
                    @if ($doc)
                        <span style="white-space:nowrap">
                            <a class="btn ghost sm" href="{{ URL::temporarySignedRoute('dashboard.documents.download', now()->addMinutes(config('aqdar.files.signed_url_minutes', 10)), ['document' => $doc]) }}">{{ __('aqdar.listings.docs.open') }}</a>
                            @if ($doc->review_status->value !== 'approved')
                                <form method="POST" action="{{ route('dashboard.documents.approve', $doc) }}" style="display:inline">@csrf<input type="hidden" name="reason" value="{{ __('aqdar.listings.docs.approve_default') }}"><button class="btn sm" type="submit">{{ __('aqdar.listings.desk.approve') }}</button></form>
                                <details style="display:inline-block">
                                    <summary class="btn ghost sm">{{ __('aqdar.listings.docs.request_replacement') }}</summary>
                                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.documents.request-replacement', $doc), 'label' => __('aqdar.listings.docs.request_replacement'), 'tone' => 'ghost'])
                                </details>
                                <details style="display:inline-block">
                                    <summary class="btn ghost sm">{{ __('aqdar.listings.desk.reject') }}</summary>
                                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.documents.reject', $doc), 'label' => __('aqdar.listings.desk.reject'), 'tone' => 'ghost'])
                                </details>
                            @endif
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.listings.docs.access_log') }}</b></div>
            @forelse ($logs as $log)
                <div class="dl" style="padding:10px 14px">
                    <span>{{ $log->actor?->name ?? '—' }} · {{ $log->action }}</span>
                    <b class="en tiny">{{ $log->created_at?->toDateTimeString() }}</b>
                </div>
            @empty
                <p class="tiny" style="padding:12px">{{ __('aqdar.listings.docs.no_access') }}</p>
            @endforelse
        </div>
    </div>
@endsection
