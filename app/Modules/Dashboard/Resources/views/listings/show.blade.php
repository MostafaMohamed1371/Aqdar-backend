@extends('dashboard::layouts.app')

@section('title', $listing->localizedTitle() ?: $listing->public_code)
@section('screen', 'ADM-02')

@php
    $financials = $listing->financials;
    $payment = $financials?->declared_payment_system?->value;
    $status = $listing->status->value;
    $canReview = in_array($status, ['submitted', 'in_review', 'resubmitted'], true);
    $rows = [
        ['contract_price', $financials?->declared_contract_price, $financials?->verified_contract_price, $financials?->contract_price_verified, 'verified_contract_price'],
        ['total_paid', $financials?->declared_total_paid, $financials?->verified_total_paid, $financials?->total_paid_verified, 'verified_total_paid'],
        ['remaining', $financials?->declared_remaining, $financials?->verified_remaining, $financials?->remaining_verified, 'verified_remaining'],
        ['overdue_count', $financials?->declared_overdue_count, $financials?->verified_overdue_count, $financials?->overdue_count_verified, 'verified_overdue_count'],
        ['overdue_amount', $financials?->declared_overdue_amount, $financials?->verified_overdue_amount, $financials?->overdue_amount_verified, 'verified_overdue_amount'],
        ['installment_amount', $financials?->declared_installment_amount, $financials?->verified_installment_amount, $financials?->installment_amount_verified, 'verified_installment_amount'],
        ['remaining_installments', $financials?->declared_remaining_installments, $financials?->verified_remaining_installments, $financials?->remaining_installments_verified, 'verified_remaining_installments'],
        ['next_due_date', $financials?->declared_next_due_date?->toDateString(), $financials?->verified_next_due_date?->toDateString(), $financials?->next_due_date_verified, 'verified_next_due_date'],
        ['seller_recovery_amount', $financials?->declared_seller_recovery_amount, $financials?->verified_seller_recovery_amount, $financials?->seller_recovery_verified, 'verified_seller_recovery_amount'],
        ['asking_price', $financials?->declared_asking_price, $financials?->verified_asking_price, $financials?->asking_price_verified, 'verified_asking_price'],
        ['cancellation_percent', $financials?->declared_cancellation_percent, $financials?->verified_cancellation_percent, $financials?->cancellation_percent_verified, 'verified_cancellation_percent'],
    ];
@endphp

@section('content')
    <div class="ptitle">
        <h2>{{ $listing->localizedTitle() ?: $listing->public_code }}</h2>
        <div class="acts">
            <a class="btn ghost" href="{{ route('dashboard.listings.index') }}">{{ __('aqdar.listings.desk.back') }}</a>
            @can('open_private_documents')
                <a class="btn ghost" href="{{ route('dashboard.documents.show', $listing) }}">{{ __('aqdar.menu.adm_03') }}</a>
            @endcan
        </div>
    </div>

    <p class="tiny">{{ __('aqdar.listings.desk.approve_publish_note') }}</p>

    <div class="kpis">
        <div class="kpi">
            <span class="ic">@include('dashboard::partials.icon', ['name' => 'doc'])</span>
            <div>
                <span>{{ __('aqdar.listings.desk.code') }}</span>
                <b class="en">{{ $listing->public_code }}</b>
            </div>
        </div>
        <div class="kpi">
            <span class="ic warn">@include('dashboard::partials.icon', ['name' => 'clock'])</span>
            <div>
                <span>{{ __('structer.status') }}</span>
                <b data-live-row="listings" data-live-id="{{ $listing->id }}" data-live-field="status">{{ __('aqdar.listings.status.'.$status) }}</b>
            </div>
        </div>
        <div class="kpi">
            <span class="ic">@include('dashboard::partials.icon', ['name' => 'eye'])</span>
            <div>
                <span>{{ __('aqdar.listings.desk.views') }}</span>
                <b class="en">{{ number_format((int) $listing->view_count) }}</b>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-h"><b>{{ __('aqdar.listings.desk.actions') }}</b></div>
        <div style="padding:12px 14px">
            @if (! empty($missingApprovedDocuments))
                <p class="tiny">{{ __('aqdar.listings.desk.documents_must_be_approved', ['types' => collect($missingApprovedDocuments)->map->value->implode(', ')]) }}</p>
            @endif
            @if ($canReview)
                @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.approve', $listing), 'label' => __('aqdar.listings.desk.approve'), 'tone' => ''])
                <form method="POST" action="{{ route('dashboard.listings.request-changes', $listing) }}" style="margin:6px 0">
                    @csrf
                    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap">
                        <input type="text" name="reason" required minlength="3" maxlength="500" placeholder="{{ __('aqdar.listings.desk.reason') }}" style="flex:1;min-width:180px">
                        <button type="submit" class="btn sm ghost">{{ __('aqdar.listings.desk.request_changes') }}</button>
                    </div>
                    <details style="margin-top:6px">
                        <summary class="tiny">{{ __('aqdar.listings.desk.field_notes') }}</summary>
                        @foreach (\App\Modules\Listing\Models\ListingStatusHistory::NOTE_SECTIONS as $section)
                            <label class="tiny" style="display:block;margin-top:6px">{{ __('aqdar.listings.desk.note_sections.'.$section) }}
                                <input type="text" name="field_notes[{{ $section }}]" maxlength="300" value="{{ old('field_notes.'.$section) }}" style="width:100%">
                            </label>
                        @endforeach
                    </details>
                </form>
                @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.reject', $listing), 'label' => __('aqdar.listings.desk.reject'), 'tone' => 'ghost'])
            @endif
            @if ($status === 'approved')
                @can('publish_listing')
                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.publish', $listing), 'label' => __('aqdar.listings.desk.publish')])
                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.archive', $listing), 'label' => __('aqdar.listings.desk.archive'), 'tone' => 'ghost'])
                @else
                    <p class="tiny">{{ __('aqdar.listings.desk.reviewer_cannot_publish') }}</p>
                @endcan
                @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.reject', $listing), 'label' => __('aqdar.listings.desk.reject'), 'tone' => 'ghost'])
            @endif
            @if (in_array($status, ['published', 'under_offer'], true))
                @can('publish_listing')
                    @if ($status === 'published')
                        @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.under-offer', $listing), 'label' => __('aqdar.listings.desk.under_offer')])
                    @else
                        @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.publish', $listing), 'label' => __('aqdar.listings.desk.publish')])
                    @endif
                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.unpublish', $listing), 'label' => __('aqdar.listings.desk.unpublish'), 'tone' => 'ghost'])
                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.hold', $listing), 'label' => __('aqdar.listings.desk.hold'), 'tone' => 'ghost'])
                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.close', $listing), 'label' => __('aqdar.listings.desk.close'), 'tone' => 'ghost'])
                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.archive', $listing), 'label' => __('aqdar.listings.desk.archive'), 'tone' => 'ghost'])
                @endcan
            @endif
            @if ($status === 'hold')
                @can('publish_listing')
                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.publish', $listing), 'label' => __('aqdar.listings.desk.publish')])
                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.unpublish', $listing), 'label' => __('aqdar.listings.desk.unpublish'), 'tone' => 'ghost'])
                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.close', $listing), 'label' => __('aqdar.listings.desk.close'), 'tone' => 'ghost'])
                    @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.archive', $listing), 'label' => __('aqdar.listings.desk.archive'), 'tone' => 'ghost'])
                @endcan
            @endif
            @if (in_array($status, ['rejected', 'archived'], true))
                @include('dashboard::partials.reason-form', ['action' => route('dashboard.listings.reopen', $listing), 'label' => __('aqdar.listings.desk.reopen'), 'tone' => 'ghost'])
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-h"><b>{{ __('aqdar.listings.desk.specs') }}</b></div>
        <div class="tiny" style="line-height:1.9;padding:12px 14px">
            {{ $listing->governorate?->localizedName() }} · {{ $listing->area?->localizedName() }}
            @if ($listing->neighborhood) · {{ $listing->neighborhood }} @endif
            · {{ $listing->unitType?->localizedName() }}
            · {{ $listing->area_sqm }} {{ __('aqdar.listings.desk.sqm') }}
            · {{ $listing->bedrooms }} {{ __('aqdar.listings.desk.bedrooms') }}
            @if ($listing->furnished) · {{ __('aqdar.listings.furnished.'.$listing->furnished) }} @endif
            @if ($listing->phase) · {{ __('aqdar.listings.desk.phase') }}: {{ $listing->phase }} @endif
            @if ($listing->parking !== null) · {{ __('aqdar.listings.desk.parking') }}: {{ $listing->parking }} @endif
            @if ($listing->unit_code) · {{ __('aqdar.listings.desk.unit_code') }}: <span class="en">{{ $listing->unit_code }}</span> @endif
            @if ($listing->offer_expires_at) · {{ __('aqdar.listings.desk.offer_expires') }}: <span class="en">{{ $listing->offer_expires_at->toDateString() }}</span> @endif
            @if ($listing->commission_policy_code) · {{ __('aqdar.listings.desk.commission_policy') }}: <span class="en">{{ $listing->commission_policy_code }}</span> @endif
        </div>
        <form method="POST" action="{{ route('dashboard.listings.assign-reviewer', $listing) }}" style="padding:0 14px 12px">
            @csrf
            <label class="tiny" for="assigned_reviewer_id">{{ __('aqdar.listings.desk.reviewer') }}</label>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <select id="assigned_reviewer_id" name="assigned_reviewer_id">
                    <option value="">{{ __('aqdar.listings.desk.unassigned') }}</option>
                    @foreach ($reviewers as $reviewer)
                        <option value="{{ $reviewer->id }}" @selected((int) $listing->assigned_reviewer_id === (int) $reviewer->id)>{{ $reviewer->name }}</option>
                    @endforeach
                </select>
                @if ($listing->assigned_reviewer_id)
                    <input type="text" name="reason" minlength="3" maxlength="500" placeholder="{{ __('aqdar.crm.reassign_reason_placeholder') }}" style="min-width:220px">
                @endif
                <button class="btn sm" type="submit">{{ __('aqdar.listings.desk.assign_reviewer') }}</button>
            </div>
        </form>
    </div>

    @if ($listing->installmentItems->isNotEmpty())
        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.listings.desk.installments') }}</b></div>
            <div class="tw">
                <table class="t">
                    <tr>
                        <th>#</th>
                        <th>{{ __('aqdar.listings.desk.field_next_due_date') }}</th>
                        <th>{{ __('aqdar.listings.desk.field_installment_amount') }}</th>
                        <th>{{ __('structer.status') }}</th>
                        <th>{{ __('aqdar.listings.desk.evidence') }}</th>
                    </tr>
                    @foreach ($listing->installmentItems as $item)
                        <tr>
                            <td class="en">{{ $item->sequence }}</td>
                            <td class="en">{{ $item->due_date?->toDateString() ?? '—' }}</td>
                            <td class="en">{{ $item->amount }}</td>
                            <td>
                                <span class="badge {{ $item->paid ? 'ok' : 'grey' }}">{{ $item->paid ? __('aqdar.listings.desk.paid') : __('aqdar.listings.desk.unpaid') }}</span>
                            </td>
                            <td>
                                @if ($item->evidence)
                                    @can('open_private_documents')
                                        <a class="en" href="{{ route('dashboard.documents.show', $listing) }}">{{ __('aqdar.listings.doc_type.'.$item->evidence->type->value) }} · {{ $item->evidence->original_name }}</a>
                                    @else
                                        <span class="en">{{ __('aqdar.listings.doc_type.'.$item->evidence->type->value) }} · {{ $item->evidence->original_name }}</span>
                                    @endcan
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-h">
            <b>{{ __('aqdar.listings.desk.financial_compare') }} · {{ $listing->public_code }}</b>
            <span class="tiny">{{ __('aqdar.listings.desk.declared_kept') }}</span>
        </div>
        <form method="POST" action="{{ route('dashboard.listings.verify-financials', $listing) }}">
            @csrf
            <div class="tw">
                <table class="t">
                    <tr>
                        <th>{{ __('aqdar.listings.desk.field') }}</th>
                        <th>{{ __('aqdar.listings.desk.declared') }}</th>
                        <th>{{ __('aqdar.listings.desk.verified') }}</th>
                        <th>{{ __('structer.status') }}</th>
                    </tr>
                    @foreach ($rows as [$field, $declared, $verified, $flag, $input])
                        <tr>
                            <td>{{ __('aqdar.listings.desk.field_'.$field) }}</td>
                            <td class="en">{{ $declared ?? '—' }}</td>
                            <td><input class="en" type="text" name="{{ $input }}" value="{{ $verified }}" style="width:140px"></td>
                            <td>
                                @if ($flag && $verified !== null)
                                    <span class="badge ok">{{ __('aqdar.listings.desk.verified_badge') }}</span>
                                @else
                                    <span class="badge grey">{{ __('aqdar.listings.desk.pending_verify') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
            <div style="padding:12px 14px;display:flex;gap:8px;flex-wrap:wrap">
                <input type="text" name="reason" required minlength="3" maxlength="500" placeholder="{{ __('aqdar.listings.desk.verify_reason') }}" style="flex:1;min-width:220px">
                <button class="btn sm" type="submit">{{ __('aqdar.listings.desk.save_verified') }}</button>
            </div>
        </form>
    </div>

    @if ($listing->media->isNotEmpty())
        <div class="card">
            <div class="card-h"><b>{{ __('aqdar.listings.desk.media') }}</b></div>
            <div class="tw">
                <table class="t">
                    <tr>
                        <th>#</th>
                        <th>{{ __('structer.status') }}</th>
                        <th>{{ __('aqdar.listings.desk.alerts') }}</th>
                        <th>{{ __('structer.actions') }}</th>
                    </tr>
                    @foreach ($listing->media as $media)
                        <tr>
                            <td class="en">{{ $media->id }}</td>
                            <td><span class="badge">{{ __('aqdar.listings.media_status.'.$media->moderation_status->value) }}</span></td>
                            <td>{{ $media->hold_reason ? __('aqdar.listings.desk.alert.'.$media->hold_reason) : '—' }}</td>
                            <td>
                                @if ($media->moderation_status->value !== 'approved')
                                    <form method="POST" action="{{ route('dashboard.listings.media.approve', [$listing, $media]) }}" style="display:inline">@csrf<button class="btn sm" type="submit">{{ __('aqdar.listings.desk.approve') }}</button></form>
                                @endif
                                @if ($media->moderation_status->value !== 'rejected')
                                    <form method="POST" action="{{ route('dashboard.listings.media.reject', [$listing, $media]) }}" style="display:inline">@csrf<input type="hidden" name="reason" value="{{ __('aqdar.listings.desk.media_reject_default') }}"><button class="btn ghost sm" type="submit">{{ __('aqdar.listings.desk.reject') }}</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-h"><b>{{ __('aqdar.listings.desk.decision_timeline') }}</b></div>
        <div class="tw">
            <table class="t">
                @forelse ($listing->statusHistory->sortByDesc('id') as $entry)
                    <tr>
                        <td class="en tiny" style="white-space:nowrap">{{ $entry->created_at?->format('Y/m/d H:i') }}</td>
                        <td style="white-space:nowrap">
                            @if ($entry->from_status){{ __('aqdar.listings.status.'.$entry->from_status->value) }} {{ app()->getLocale() === 'ar' ? '←' : '→' }} @endif
                            <b>{{ __('aqdar.listings.status.'.$entry->to_status?->value) }}</b>
                        </td>
                        <td>
                            {{ $entry->reason }}
                            @foreach ((array) $entry->field_notes as $section => $note)
                                @if ($note)<div class="tiny">{{ __('aqdar.listings.desk.note_sections.'.$section) }}: {{ $note }}</div>@endif
                            @endforeach
                        </td>
                        <td>{{ $entry->actor?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td>{{ __('aqdar.listings.desk.timeline_empty') }}</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    @if ($listing->contact_flagged)
        <div class="alert">
            <b>{{ __('aqdar.listings.desk.alert.'.$listing->contact_flag_reason) }}</b>
            <div class="tiny">{{ __('aqdar.listings.desk.held_for_staff') }}</div>
        </div>
    @endif
@endsection
