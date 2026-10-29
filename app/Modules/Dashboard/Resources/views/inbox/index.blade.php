@extends('dashboard::layouts.app')

@section('title', __('aqdar.notifications.inbox'))
@section('screen', 'staff-inbox')

@section('content')
    <div class="ptitle">
        <h2><x-bilingual key="aqdar.notifications.inbox" /></h2>
    </div>
    <p class="tiny">{{ __('aqdar.notifications.inbox_note') }}</p>
    <div class="tw">
        <table class="t">
            <tr>
                <th>{{ __('aqdar.notifications.event') }}</th>
                <th>{{ __('aqdar.notifications.body') }}</th>
                <th>{{ __('aqdar.cases.updated') }}</th>
                <th>{{ __('structer.actions') }}</th>
            </tr>
            @forelse ($rows as $row)
                <tr>
                    <td>
                        <b>{{ $row->localizedTitle() }}</b>
                        @if ($row->read_at === null)
                            <span class="badge warn">{{ __('aqdar.notifications.unread') }}</span>
                        @endif
                    </td>
                    <td>{{ $row->localizedBody() }}</td>
                    <td class="en">{{ $row->created_at?->format('Y-m-d H:i') }}</td>
                    <td>
                        @if ($row->read_at === null)
                            <form method="POST" action="{{ route('dashboard.inbox.read', $row) }}">
                                @csrf
                                <button class="btn ghost sm" type="submit">{{ __('aqdar.notifications.mark_read') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">{{ __('aqdar.notifications.empty_inbox') }}</td></tr>
            @endforelse
        </table>
    </div>
    {{ $rows->links() }}
@endsection
