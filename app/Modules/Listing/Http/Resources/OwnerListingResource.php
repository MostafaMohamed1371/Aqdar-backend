<?php

namespace App\Modules\Listing\Http\Resources;

use App\Core\Enums\ListingStatus;
use App\Modules\Listing\Http\Resources\Concerns\MapsCatalog;
use App\Modules\Listing\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class OwnerListingResource extends JsonResource
{
    use MapsCatalog;

    public function toArray(Request $request): array
    {
        $financials = $this->financials;

        return [
            'id' => $this->id,
            'public_code' => $this->public_code,
            'review_reference' => $this->review_reference,
            'status' => $this->status?->value,
            'version' => $this->version,
            'segment' => $this->segment?->value,
            'distressed_child' => $this->distressed_child?->value,
            'category' => $this->named($this->category),
            'governorate' => $this->named($this->governorate),
            'area' => $this->named($this->area),
            'developer' => $this->named($this->developer),
            'project' => $this->named($this->project),
            'unit_type' => $this->named($this->unitType),
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'area_sqm' => $this->area_sqm !== null ? (float) $this->area_sqm : null,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'floor' => $this->floor,
            'finishing' => $this->finishing,
            'furnished' => $this->furnished,
            'handover_date' => $this->handover_date?->toDateString(),
            'neighborhood' => $this->neighborhood,
            'phase' => $this->phase,
            'unit_code' => $this->unit_code,
            'parking' => $this->parking,
            'offer_expires_at' => $this->offer_expires_at?->toIso8601String(),
            'commission_policy_code' => $this->commission_policy_code,
            'view_count' => (int) $this->view_count,
            'lat' => $this->lat !== null ? (float) $this->lat : null,
            'lng' => $this->lng !== null ? (float) $this->lng : null,
            'amenity_ids' => $this->amenity_ids ?? [],
            'tag_ids' => $this->tag_ids ?? [],
            'amenities' => $this->namedAmenities($this->amenity_ids),
            'tags' => $this->namedTags($this->tag_ids),
            'favorite_count' => (int) ($this->favorites_count ?? $this->favorites()->count()),
            'media_meta' => $this->media_meta ?? [],
            'documents_meta' => $this->documents_meta ?? [],
            'documents' => ListingDocumentResource::collection($this->whenLoaded('documents')),
            'media' => ListingMediaResource::collection($this->whenLoaded('media')),
            'contact_flagged' => $this->contact_flagged,
            'contact_flag_reason' => $this->contact_flag_reason,
            'consent_at' => $this->consent_at,
            'submitted_at' => $this->submitted_at,
            'published_at' => $this->published_at,
            'financials' => $financials === null ? null : [
                'declared_contract_price' => $this->money($financials->declared_contract_price),
                'declared_contract_date' => $financials->declared_contract_date?->toDateString(),
                'declared_total_paid' => $this->money($financials->declared_total_paid),
                'declared_remaining' => $this->money($financials->declared_remaining),
                'declared_overdue_count' => $financials->declared_overdue_count,
                'declared_overdue_amount' => $this->money($financials->declared_overdue_amount),
                'declared_next_due_date' => $financials->declared_next_due_date,
                'declared_installment_amount' => $this->money($financials->declared_installment_amount),
                'declared_remaining_installments' => $financials->declared_remaining_installments,
                'declared_payment_system' => $financials->declared_payment_system?->value,
                'declared_seller_recovery_amount' => $this->money($financials->declared_seller_recovery_amount),
                'declared_asking_price' => $this->money($financials->declared_asking_price),
                'declared_cancellation_percent' => $this->money($financials->declared_cancellation_percent),
                'declared_assignment_restrictions' => $financials->declared_assignment_restrictions,
            ],
            'installment_items' => $this->installmentItems->map(fn ($item) => [
                'id' => $item->id,
                'sequence' => $item->sequence,
                'due_date' => $item->due_date,
                'amount' => $this->money($item->amount),
                'note' => $item->note,
                'paid' => (bool) $item->paid,
                'evidence_document_id' => $item->evidence_document_id,
            ])->values(),
            'previous_snapshot' => $this->when(
                $this->relationLoaded('fieldSnapshots'),
                fn () => $this->previousSnapshotPayload(),
            ),
            'reviewer_notes' => $this->when(
                $this->relationLoaded('statusHistory'),
                fn () => $this->reviewerNotes(),
            ),
        ];
    }

    private function money(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /** @return array{version: int, payload: array<string, mixed>}|null */
    private function previousSnapshotPayload(): ?array
    {
        $snapshot = $this->previousSnapshot();

        if ($snapshot === null) {
            return null;
        }

        return [
            'version' => (int) $snapshot->version,
            'payload' => $snapshot->payload ?? [],
        ];
    }

    /** @return list<string> */
    private function reviewerNotes(): array
    {
        return $this->statusHistory
            ->filter(fn ($row) => $row->to_status === ListingStatus::NeedsChanges)
            ->map(fn ($row) => trim((string) $row->reason))
            ->filter()
            ->values()
            ->all();
    }
}
