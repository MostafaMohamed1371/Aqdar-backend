@php
    use App\Core\Enums\CaseDocumentKind;
    use App\Core\Enums\CaseStage;
    use App\Core\Enums\CaseStatus;
    use App\Core\Enums\EscrowMethod;
    use App\Core\Enums\EscrowStatus;
    use App\Core\Enums\ExtractionStatus;
    $item = $caseModel;
    $financeOnly = $financeOnly ?? false;
    $allowedStages = $allowedStages ?? CaseStage::cases();
    $canAdvanceFinance = $canAdvanceFinance ?? true;
    $checklist = $checklist ?? collect();
    $canManageEscrow = $canManageEscrow ?? false;
    $canRecordEscrow = $canRecordEscrow ?? false;
    $canApproveEscrow = $canApproveEscrow ?? false;
    $canReviewDocuments = $canReviewDocuments ?? false;
@endphp
@extends('dashboard::layouts.app')

@section('title', $item->public_code)
@section('screen', 'ADM-09')

@section('content')
    <div class="ptitle">
        <h2>{{ $item->public_code }} · {{ $item->buyer?->name }}</h2>
        <div class="acts">
            <a class="btn ghost" href="{{ route('dashboard.cases.index') }}">{{ __('aqdar.listings.desk.back') }}</a>
            <span class="badge {{ $item->stageBadgeTone() }}" data-live-row="cases" data-live-id="{{ $item->id }}" data-live-field="stage">{{ __('aqdar.cases.stage.'.$item->internal_stage?->value) }}</span>
            <span class="badge {{ $item->lifecycleBadgeTone() }}" data-live-row="cases" data-live-id="{{ $item->id }}" data-live-field="status">{{ __('aqdar.cases.lifecycle.'.$item->status?->value) }}</span>
        </div>
    </div>
    <p class="tiny">{{ __('aqdar.cases.client_sees') }}: {{ __('aqdar.cases.client.'.$item->client_label) }} · {{ __('aqdar.cases.missing_docs') }}: {{ $item->missing_documents_count }}</p>
    <p class="tiny">{{ $financeOnly ? __('aqdar.cases.finance_only_note') : __('aqdar.cases.manual_docs') }}</p>

    <div class="cols2">
        <div class="card">
            <div class="card-h">
                <b>{{ __('aqdar.crm.owner') }}</b>
                @if ($canAssign)
                    @include('dashboard::partials.assign-select', ['action' => route('dashboard.cases.assign', $item), 'current' => $item->assigned_to, 'assignable' => $assignable])
                @else
                    <span>{{ $item->assignee?->name ?? __('aqdar.crm.unassigned') }}</span>
                @endif
            </div>
            <div class="dl"><span>{{ __('aqdar.crm.buyer') }}</span><b>{{ $item->buyer?->name }}</b></div>
            <div class="dl"><span>{{ __('aqdar.cases.seller') }}</span><b>{{ $item->seller?->name ?? '—' }}</b></div>
            <div class="dl"><span>{{ __('aqdar.crm.listing') }}</span><b>{{ $item->listing?->localizedTitle() ?? '—' }}</b></div>
            @if ($item->lead && ! $financeOnly)
                <div class="dl"><span>{{ __('aqdar.crm.code') }}</span><a class="btn ghost sm" href="{{ route('dashboard.leads.show', $item->lead) }}">{{ $item->lead->public_code }}</a></div>
            @endif
            <div class="dl"><span>{{ __('aqdar.cases.last_transition') }}</span><b class="en">{{ $item->last_transition_at?->format('Y-m-d H:i') ?? '—' }}</b></div>
        </div>

        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.cases.internal_stage') }}</b></div>
            @if ($financeOnly && ! $canAdvanceFinance)
                <p class="tiny" style="padding:12px 14px">{{ __('aqdar.cases.finance_not_ready') }}</p>
            @else
                <form method="POST" action="{{ route('dashboard.cases.stage', $item) }}" class="toolbar" style="flex-wrap:wrap">
                    @csrf
                    <select name="stage">
                        @foreach ($allowedStages as $stage)
                            <option value="{{ $stage->value }}" @selected($item->internal_stage === $stage)>{{ __('aqdar.cases.stage.'.$stage->value) }} → {{ __('aqdar.cases.client.'.$stage->clientLabel()) }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="reason" required minlength="3" value="{{ __('aqdar.crm.status_default') }}">
                    @unless ($financeOnly)
                        <input type="text" name="internal_note" placeholder="{{ __('aqdar.cases.internal_note') }}">
                        <input type="text" name="requested_document_note" placeholder="{{ __('aqdar.cases.request_doc') }}">
                    @endunless
                    <button class="btn sm" type="submit">{{ __('aqdar.crm.update_status') }}</button>
                </form>
            @endif
            @unless ($financeOnly)
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
            @endunless
        </div>
    </div>

    @unless ($financeOnly)
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
                                @if ($canReviewDocuments && $doc->extraction?->status === ExtractionStatus::PendingReview)
                                    <p class="tiny" style="margin:6px 0 2px">{{ __('aqdar.extraction.title') }} · {{ __('aqdar.extraction.pending') }}</p>
                                    <form method="POST" action="{{ route('dashboard.cases.documents.extraction', $doc) }}" class="toolbar">
                                        @csrf
                                        <input type="hidden" name="status" value="confirmed">
                                        @foreach ((array) config('aqdar.ocr.fields') as $field)
                                            <input name="fields[{{ $field }}]" placeholder="{{ __('aqdar.extraction.fields.'.$field) }}" class="en">
                                        @endforeach
                                        <button class="btn sm" type="submit">{{ __('aqdar.extraction.confirm') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('dashboard.cases.documents.extraction', $doc) }}" class="toolbar">
                                        @csrf
                                        <input type="hidden" name="status" value="rejected">
                                        <button class="btn ghost sm" type="submit">{{ __('aqdar.extraction.reject') }}</button>
                                    </form>
                                @elseif ($doc->extraction)
                                    <span class="tiny">{{ $doc->extraction->status === ExtractionStatus::Confirmed ? __('aqdar.extraction.confirmed') : __('aqdar.extraction.rejected') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2">—</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    @endunless

    @unless ($financeOnly)
        <div class="card" style="margin-top:14px" data-section="checklist">
            <div class="card-h"><b>{{ __('aqdar.checklist.title') }}</b></div>
            <table class="t">
                @foreach ($checklist as $row)
                    <tr>
                        <td>{{ __('aqdar.checklist.items.'.$row->item_key) }}</td>
                        <td>
                            <span class="badge {{ $row->isDone() ? 'ok' : 'grey' }}">{{ $row->isDone() ? __('aqdar.checklist.done') : __('aqdar.checklist.pending') }}</span>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('dashboard.cases.checklist', ['transferCase' => $item, 'item' => $row->item_key]) }}">
                                @csrf
                                <input type="hidden" name="done" value="{{ $row->isDone() ? 0 : 1 }}">
                                <button class="btn ghost sm" type="submit">{{ $row->isDone() ? __('aqdar.checklist.undo') : __('aqdar.checklist.mark_done') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endunless

    @if ($canManageEscrow)
        <div class="card" style="margin-top:14px" data-section="escrow">
            <div class="card-h"><b>{{ __('aqdar.escrow.title') }}</b><span class="tiny">{{ __('aqdar.escrow.intro') }}</span></div>
            <div class="tw">
                <table class="t">
                    <tr>
                        <th>{{ __('aqdar.escrow.col_when') }}</th>
                        <th>{{ __('aqdar.escrow.amount') }}</th>
                        <th>{{ __('aqdar.escrow.method') }}</th>
                        <th>{{ __('aqdar.escrow.col_status') }}</th>
                        <th>{{ __('aqdar.escrow.col_by') }}</th>
                        <th>{{ __('aqdar.escrow.col_actions') }}</th>
                    </tr>
                    @forelse ($item->escrowPayments as $payment)
                        <tr>
                            <td class="en">{{ $payment->received_at?->format('Y-m-d') }}</td>
                            <td class="en">{{ number_format((float) $payment->amount, 2) }}</td>
                            <td>{{ __('aqdar.escrow.methods.'.$payment->method->value) }}{{ $payment->reference ? ' · '.$payment->reference : '' }}</td>
                            <td><span class="badge {{ $payment->status === EscrowStatus::Held ? 'gold' : 'ok' }}">{{ __('aqdar.escrow.statuses.'.$payment->status->value) }}</span></td>
                            <td>{{ $payment->recordedBy?->name ?? '—' }}</td>
                            <td>
                                @if ($canApproveEscrow && ! $payment->status->isSettled())
                                    <form method="POST" action="{{ route('dashboard.escrow.settle', $payment) }}" class="toolbar">
                                        @csrf
                                        <input name="note" required minlength="3" maxlength="500" placeholder="{{ __('aqdar.escrow.settle_note') }}">
                                        <button class="btn sm" name="status" value="released" type="submit">{{ __('aqdar.escrow.release') }}</button>
                                        <button class="btn ghost sm" name="status" value="refunded" type="submit">{{ __('aqdar.escrow.refund') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="tiny">{{ __('aqdar.escrow.empty') }}</td></tr>
                    @endforelse
                </table>
            </div>
            @if ($canRecordEscrow)
                <form method="POST" action="{{ route('dashboard.cases.escrow.store', $item) }}" class="toolbar" style="margin-top:12px">
                    @csrf
                    <input name="amount" type="number" step="0.01" min="0.01" required placeholder="{{ __('aqdar.escrow.amount') }}" class="en">
                    <select name="method">
                        @foreach (EscrowMethod::cases() as $method)
                            <option value="{{ $method->value }}">{{ __('aqdar.escrow.methods.'.$method->value) }}</option>
                        @endforeach
                    </select>
                    <input name="reference" maxlength="128" placeholder="{{ __('aqdar.escrow.reference') }}" class="en">
                    <input name="received_at" type="date" class="en">
                    <input name="note" maxlength="500" placeholder="{{ __('aqdar.escrow.note') }}">
                    <button class="btn sm" type="submit">{{ __('aqdar.escrow.record') }}</button>
                </form>
            @endif
        </div>
    @endif

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
