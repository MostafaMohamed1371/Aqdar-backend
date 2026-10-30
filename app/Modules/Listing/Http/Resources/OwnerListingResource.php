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
                'verified_contract_price' => $this->money($financials->verified_contract_price),
                'contract_price_verified' => (bool) $financials->contract_price_verified,
                'verified_total_paid' => $this->money($financials->verified_total_paid),
                'total_paid_verified' => (bool) $financials->total_paid_verified,
                'verified_remaining' => $this->money($financials->verified_remaining),
                'remaining_verified' => (bool) $financials->remaining_verified,
                'verified_overdue_count' => $financials->verified_overdue_count,
                'overdue_count_verified' => (bool) $financials->overdue_count_verified,
                'verified_overdue_amount' => $this->money($financials->verified_overdue_amount),
                'overdue_amount_verified' => (bool) $financials->overdue_amount_verified,
                'verified_next_due_date' => $financials->verified_next_due_date?->toDateString(),
                'next_due_date_verified' => (bool) $financials->next_due_date_verified,
                'verified_installment_amount' => $this->money($financials->verified_installment_amount),
                'installment_amount_verified' => (bool) $financials->installment_amount_verified,
                'verified_remaining_installments' => $financials->verified_remaining_installments,
                'remaining_installments_verified' => (bool) $financials->remaining_installments_verified,
                'verified_seller_recovery_amount' => $this->money($financials->verified_seller_recovery_amount),
                'seller_recovery_verified' => (bool) $financials->seller_recovery_verified,
                'verified_asking_price' => $this->money($financials->verified_asking_price),
                'asking_price_verified' => (bool) $financials->asking_price_verified,
                'verified_cancellation_percent' => $this->money($financials->verified_cancellation_percent),
                'cancellation_percent_verified' => (bool) $financials->cancellation_percent_verified,
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
            'reviewer_field_notes' => $this->when(
                $this->relationLoaded('statusHistory'),
                fn () => $this->reviewerFieldNotes(),
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

    /** @return list<array{section: string, note: string}> */
    private function reviewerFieldNotes(): array
    {
        if ($this->status !== ListingStatus::NeedsChanges) {
            return [];
        }

        $latest = $this->statusHistory
            ->filter(fn ($row) => $row->to_status === ListingStatus::NeedsChanges)
            ->sortByDesc('id')
            ->first();

        return collect($latest?->field_notes ?? [])
            ->map(fn (string $note, string $section) => ['section' => $section, 'note' => $note])
            ->values()
            ->all();
    }
}
