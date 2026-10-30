@extends('dashboard::layouts.app')

@section('title', __('aqdar.users.edit'))
@section('screen', 'ADM-04')

@section('content')
    <div class="ptitle">
        <h2>{{ __('aqdar.users.edit') }}</h2>
    </div>

    <div class="card" style="max-width:640px">
        @include('dashboard::users._form', [
            'action' => route('dashboard.users.update', $user),
            'method' => 'PUT',
            'user' => $user,
            'submit' => __('structer.save'),
        ])
    </div>

    <div class="cols2" style="margin-top:14px">
        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.users.consent_card') }}</b></div>
            <div class="tw">
                <table class="t">
                    <tr><th>{{ __('aqdar.auth.consent_terms') }}</th><td class="en">{{ $user->consent_terms_at?->format('Y/m/d H:i') ?? '—' }}</td></tr>
                    <tr><th>{{ __('aqdar.auth.consent_privacy') }}</th><td class="en">{{ $user->consent_privacy_at?->format('Y/m/d H:i') ?? '—' }}</td></tr>
                    <tr><th>{{ __('aqdar.users.consent_version') }}</th><td class="en">{{ $user->consent_version ?? '—' }}</td></tr>
                    <tr><th>{{ __('aqdar.users.marketing_opt_in') }}</th><td>{{ $user->notificationPreference?->marketing_opt_in ? __('aqdar.users.yes') : __('aqdar.users.no') }}</td></tr>
                    <tr><th>{{ __('aqdar.users.suspended_label') }}</th><td>{{ $user->suspended_at ? $user->suspended_at->format('Y/m/d').' · '.$user->suspend_reason : '—' }}</td></tr>
                    <tr><th>{{ __('aqdar.users.close_requested_label') }}</th><td class="en">{{ $user->close_requested_at?->format('Y/m/d') ?? '—' }}</td></tr>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.users.account_history') }}</b></div>
            <div class="tw">
                <table class="t">
                    @forelse ($history as $row)
                        <tr>
                            <td class="en tiny" style="white-space:nowrap">{{ $row->created_at?->format('Y/m/d H:i') }}</td>
                            <td>{{ \App\Modules\Dashboard\Services\AuditSearchService::actionLabel($row->action) }}</td>
                            <td class="tiny" style="max-width:260px;overflow-wrap:anywhere">{{ is_array($row->meta) && ! empty($row->meta['reason']) ? $row->meta['reason'] : \App\Modules\Dashboard\Services\AuditSearchService::details($row) }}</td>
                        </tr>
                    @empty
                        <tr><td>{{ __('aqdar.users.history_empty') }}</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
@endsection
