@php
    use App\Core\Enums\CaseDocumentKind;
    use App\Core\Enums\CaseStage;
    use App\Core\Enums\CaseStatus;
    $item = $caseModel;
@endphp
@extends('dashboard::layouts.app')

@section('title', $item->public_code)
@section('screen', 'ADM-09')

@section('content')
    <div class="ptitle">
        <h2>{{ $item->public_code }} · {{ $item->buyer?->name }}</h2>
        <div class="acts">
            <a class="btn ghost" href="{{ route('dashboard.cases.index') }}">{{ __('aqdar.listings.desk.back') }}</a>
            <span class="badge {{ $item->stageBadgeTone() }}">{{ __('aqdar.cases.stage.'.$item->internal_stage?->value) }}</span>
            <span class="badge {{ $item->lifecycleBadgeTone() }}">{{ __('aqdar.cases.lifecycle.'.$item->status?->value) }}</span>
        </div>
    </div>
    <p class="tiny">{{ __('aqdar.cases.client_sees') }}: {{ __('aqdar.cases.client.'.$item->client_label) }} · {{ __('aqdar.cases.missing_docs') }}: {{ $item->missing_documents_count }}</p>
    <p class="tiny">{{ __('aqdar.cases.manual_docs') }}</p>

    <div class="cols2">
        <div class="card">
            <div class="card-h">
                <b>{{ __('aqdar.crm.owner') }}</b>
                @if ($canAssign)
                    <form method="POST" action="{{ route('dashboard.cases.assign', $item) }}">
                        @csrf
                        <select name="assigned_to" onchange="this.form.submit()">
                            <option value="">{{ __('aqdar.crm.unassigned') }}</option>
                            @foreach ($assignable as $staff)
                                <option value="{{ $staff->id }}" @selected((int) $item->assigned_to === (int) $staff->id)>{{ $staff->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @else
                    <span>{{ $item->assignee?->name ?? __('aqdar.crm.unassigned') }}</span>
                @endif
            </div>
            <div class="dl"><span>{{ __('aqdar.crm.buyer') }}</span><b>{{ $item->buyer?->name }}</b></div>
            <div class="dl"><span>{{ __('aqdar.cases.seller') }}</span><b>{{ $item->seller?->name ?? '—' }}</b></div>
            <div class="dl"><span>{{ __('aqdar.crm.listing') }}</span><b>{{ $item->listing?->localizedTitle() ?? '—' }}</b></div>
            @if ($item->lead)
                <div class="dl"><span>{{ __('aqdar.crm.code') }}</span><a class="btn ghost sm" href="{{ route('dashboard.leads.show', $item->lead) }}">{{ $item->lead->public_code }}</a></div>
            @endif
            <div class="dl"><span>{{ __('aqdar.cases.last_transition') }}</span><b class="en">{{ $item->last_transition_at?->format('Y-m-d H:i') ?? '—' }}</b></div>
        </div>

        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.cases.internal_stage') }}</b></div>
            <form method="POST" action="{{ route('dashboard.cases.stage', $item) }}" class="toolbar" style="flex-wrap:wrap">
                @csrf
                <select name="stage">
                    @foreach (CaseStage::cases() as $stage)
                        <option value="{{ $stage->value }}" @selected($item->internal_stage === $stage)>{{ __('aqdar.cases.stage.'.$stage->value) }} → {{ __('aqdar.cases.client.'.$stage->clientLabel()) }}</option>
                    @endforeach
                </select>
                <input type="text" name="reason" required minlength="3" value="{{ __('aqdar.crm.status_default') }}">
                <input type="text" name="internal_note" placeholder="{{ __('aqdar.cases.internal_note') }}">
                <input type="text" name="requested_document_note" placeholder="{{ __('aqdar.cases.request_doc') }}">
                <button class="btn sm" type="submit">{{ __('aqdar.crm.update_status') }}</button>
            </form>
            <form method="POST" action="{{ route('dashboard.cases.lifecycle', $item) }}" class="toolbar">
                @csrf
                <select name="status">
                    @foreach (CaseStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($item->status === $status)>{{ __('aqdar.cases.lifecycle.'.$status->value) }}</option>
                    @endforeach
                </select>
                <input type="text" name="reason" required minlength="3" value="{{ __('aqdar.crm.status_default') }}">
                <button class="btn ghost sm" type="submit">{{ __('aqdar.crm.update_status') }}</button>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:14px">
        <div class="card-h"><b>{{ __('aqdar.cases.upload_letter') }}</b></div>
        <form method="POST" action="{{ route('dashboard.cases.documents.store', $item) }}" enctype="multipart/form-data" class="toolbar">
            @csrf
            <select name="kind">
                @foreach (CaseDocumentKind::cases() as $kind)
                    <option value="{{ $kind->value }}">{{ __('aqdar.cases.doc_kind.'.$kind->value) }}</option>
                @endforeach
            </select>
            <input type="file" name="file" required>
            <button class="btn sm" type="submit">{{ __('aqdar.cases.upload_letter') }}</button>
        </form>
        <div class="tw">
            <table class="t">
                <tr>
                    <th>{{ __('aqdar.cases.doc_kind.developer_approval') }}</th>
                    <th>{{ __('structer.actions') }}</th>
                </tr>
                @forelse ($item->documents as $doc)
                    <tr>
                        <td>{{ __('aqdar.cases.doc_kind.'.$doc->kind?->value) }} · {{ $doc->original_name }}</td>
                        <td>
                            <a class="btn ghost sm" href="{{ URL::temporarySignedRoute('dashboard.cases.documents.download', now()->addMinutes(config('aqdar.files.signed_url_minutes', 10)), ['caseDocument' => $doc]) }}">{{ __('aqdar.listings.docs.open') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2">—</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    <div class="card" style="margin-top:14px">
        <div class="card-h"><b>{{ __('aqdar.crm.activity') }}</b></div>
        <div class="tw">
            <table class="t">
                <tr>
                    <th>{{ __('aqdar.cases.internal_stage') }}</th>
                    <th>{{ __('aqdar.cases.client_sees') }}</th>
                    <th>{{ __('aqdar.crm.owner') }}</th>
                    <th>{{ __('aqdar.cases.internal_note') }}</th>
                </tr>
                @forelse ($item->history as $row)
                    <tr>
                        <td>
                            @if ($row->new_status)
                                {{ $row->old_status ? __('aqdar.cases.lifecycle.'.$row->old_status->value) : '—' }}
                                → {{ __('aqdar.cases.lifecycle.'.$row->new_status->value) }}
                            @else
                                {{ $row->old_stage?->value ? __('aqdar.cases.stage.'.$row->old_stage->value) : '—' }}
                                → {{ $row->new_stage?->value ? __('aqdar.cases.stage.'.$row->new_stage->value) : '—' }}
                            @endif
                        </td>
                        <td>
                            @if ($row->new_status)
                                {{ __('aqdar.cases.lifecycle.'.$row->new_status->value) }}
                            @elseif ($row->new_stage)
                                {{ __('aqdar.cases.client.'.$row->new_stage->clientLabel()) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->actor?->name }} · {{ $row->reason }} · <span class="en">{{ $row->created_at?->format('Y-m-d H:i') }}</span></td>
                        <td>{{ $row->internal_note ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">—</td></tr>
                @endforelse
            </table>
        </div>
    </div>
@endsection
