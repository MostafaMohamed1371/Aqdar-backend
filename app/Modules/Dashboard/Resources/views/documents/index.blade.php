@extends('dashboard::layouts.app')

@section('title', __('aqdar.menu.adm_03'))
@section('screen', 'ADM-03')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.menu.adm_03" /></h2>
        <p class="tiny">{{ __('aqdar.listings.docs.signed_note') }}</p>
    </div>

    <form method="GET" action="{{ route('dashboard.documents.index') }}" class="toolbar">
        @if ($tab !== 'pending')
            <input type="hidden" name="status" value="{{ $tab }}">
        @endif
        <div class="q">
            @include('dashboard::partials.icon', ['name' => 'search'])
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('aqdar.listings.docs.search') }}">
        </div>
        @include('dashboard::partials.date-range')
        <button type="submit" class="btn ghost">{{ __('aqdar.search') }}</button>
    </form>

    <div class="ctabs">
        @foreach (['all', 'pending', 'approved', 'rejected'] as $key)
            <a href="{{ route('dashboard.documents.index', array_filter(['status' => $key === 'pending' ? null : $key, 'q' => $search ?: null])) }}" @class(['on' => $tab === $key])>
                {{ __('aqdar.listings.docs.tab_'.$key) }}
                <em>{{ number_format($counts[$key] ?? 0) }}</em>
            </a>
        @endforeach
    </div>

    <div class="tw">
        <table class="t">
            <tr>
                <th>{{ __('aqdar.listings.docs.owner') }}</th>
                @include('dashboard::partials.sort-th', ['key' => 'type', 'label' => __('aqdar.listings.docs.type')])
                <th>{{ __('aqdar.listings.docs.file') }}</th>
                <th>{{ __('aqdar.listings.desk.code') }}</th>
                @include('dashboard::partials.sort-th', ['key' => 'added', 'label' => __('aqdar.listings.desk.added')])
                @include('dashboard::partials.sort-th', ['key' => 'status', 'label' => __('structer.status')])
                <th>{{ __('structer.actions') }}</th>
            </tr>
            @forelse ($documents as $document)
                <tr>
                    <td>{{ $document->listing?->owner?->name ?? '—' }}</td>
                    <td>{{ __('aqdar.listings.doc_type.'.$document->type->value) }}</td>
                    <td class="en">{{ $document->original_name }}</td>
                    <td class="en">{{ $document->listing?->public_code }}</td>
                    <td class="en">{{ $document->created_at?->toDateString() }}</td>
                    <td><span class="badge">{{ __('aqdar.listings.doc_status.'.$document->review_status->value) }}</span></td>
                    <td style="white-space:nowrap">
                        <a class="btn ghost sm" href="{{ URL::temporarySignedRoute('dashboard.documents.download', now()->addMinutes(config('aqdar.files.signed_url_minutes', 10)), ['document' => $document]) }}">{{ __('aqdar.listings.docs.open') }}</a>
                        @if ($document->listing)
                            <a class="btn ghost sm" href="{{ route('dashboard.documents.show', $document->listing) }}">{{ __('aqdar.listings.desk.view') }}</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">{{ __('aqdar.listings.docs.empty') }}</td></tr>
            @endforelse
        </table>
    </div>
    {{ $documents->links() }}
@endsection
