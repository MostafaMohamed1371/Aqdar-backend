<?php

namespace App\Modules\Dashboard\Support;

use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;

final class DeskLiveRow
{
    /**
     * @return array<string, mixed>
     */
    public static function listing(Listing $listing): array
    {
        $status = $listing->status?->value ?? 'in_review';

        return [
            'id' => (int) $listing->id,
            'public_code' => $listing->public_code,
            'title' => $listing->localizedTitle() ?: '—',
            'status' => $status,
            'status_label' => __('aqdar.listings.status.'.$status),
            'status_tone' => $listing->statusBadgeTone(),
            'market' => $listing->marketLabel(),
            'governorate' => $listing->governorate?->localizedName() ?? '—',
            'developer' => $listing->developer?->localizedName() ?? '—',
            'created_at' => $listing->created_at?->toDateString(),
            'url' => route('dashboard.listings.show', $listing),
            'view_label' => __('aqdar.listings.desk.view'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function lead(Lead $lead): array
    {
        $status = $lead->status?->value ?? 'new';

        return [
            'id' => (int) $lead->id,
            'public_code' => $lead->public_code,
            'title' => $lead->buyer?->name ?: ('USR-'.($lead->buyer_id ?? '')),
            'status' => $status,
            'status_label' => __('aqdar.crm.status.'.$status),
            'status_tone' => $lead->statusBadgeTone(),
            'listing_code' => $lead->listing?->public_code ?? '—',
            'url' => route('dashboard.leads.show', $lead),
            'view_label' => __('aqdar.listings.desk.view'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function request(BuyerRequest $request): array
    {
        $status = $request->status?->value ?? 'new';

        return [
            'id' => (int) $request->id,
            'public_code' => $request->public_code,
            'title' => $request->buyer?->name ?: ('USR-'.($request->buyer_id ?? '')),
            'status' => $status,
            'status_label' => __('aqdar.requests.status.'.$status),
            'status_tone' => $request->statusBadgeTone(),
            'url' => route('dashboard.requests.show', $request),
            'view_label' => __('aqdar.listings.desk.view'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function transferCase(TransferCase $case): array
    {
        $stage = $case->internal_stage?->value ?? '';
        $status = $case->status?->value ?? 'open';

        return [
            'id' => (int) $case->id,
            'public_code' => $case->public_code,
            'title' => $case->listing?->public_code ?? $case->public_code,
            'status' => $status,
            'status_label' => __('aqdar.cases.lifecycle.'.$status),
            'status_tone' => method_exists($case, 'lifecycleBadgeTone') ? $case->lifecycleBadgeTone() : 'grey',
            'stage' => $stage,
            'stage_label' => $stage !== '' ? __('aqdar.cases.stage.'.$stage) : '',
            'url' => route('dashboard.cases.show', $case),
            'view_label' => __('aqdar.listings.desk.view'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(ListingDocument $document): array
    {
        $status = $document->review_status?->value ?? 'pending';

        return [
            'id' => (int) $document->id,
            'public_code' => $document->listing?->public_code ?? '—',
            'title' => $document->original_name ?: ($document->type?->value ?? 'document'),
            'status' => $status,
            'status_label' => __('aqdar.listings.doc_status.'.$status),
            'status_tone' => 'warn',
            'url' => $document->listing_id
                ? route('dashboard.documents.show', $document->listing_id)
                : route('dashboard.documents.index'),
            'view_label' => __('aqdar.listings.desk.view'),
        ];
    }
}
