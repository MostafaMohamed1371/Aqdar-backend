<?php

namespace App\Modules\Listing\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\DistressedChild;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\MarketSegment;
use App\Core\Enums\Permission;
use App\Core\Enums\PublicCodePrefix;
use App\Core\Identity\PublicCodeGenerator;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Category\Models\Category;
use App\Modules\Dashboard\Support\DeskRealtime;
use App\Modules\Listing\Models\InstallmentItem;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingFieldSnapshot;
use App\Modules\Listing\Models\ListingStatusHistory;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListingService
{
    public function __construct(
        private PublicCodeGenerator $codes,
        private ListingStateMachine $states,
        private ContactLeakScanner $scanner,
        private AuditLogger $audit,
        private ListingFileService $files,
        private NotificationService $notifications,
        private ListingModerationService $moderation,
    ) {}

    public function start(User $user, array $data = []): Listing
    {
        return DB::transaction(function () use ($user, $data): Listing {
            $listing = Listing::query()->create([
                'public_code' => $this->codes->next(PublicCodePrefix::Listing),
                'user_id' => $user->id,
                'status' => ListingStatus::Draft,
                'version' => 0,
            ]);

            $listing->financials()->create([]);

            if ($data !== []) {
                $this->applyStep($listing, 'category', $data);
            }

            if (! $user->is_seller) {
                $user->forceFill(['is_seller' => true])->save();
            }

            $this->recordHistory($listing, null, ListingStatus::Draft, $user, 'draft_created');

            return $listing->fresh(['financials', 'installmentItems', 'category', 'governorate', 'area', 'developer', 'project', 'unitType']) ?? $listing;
        });
    }

    public function saveStep(Listing $listing, string $step, array $data, User $user): Listing
    {
        if (! $listing->isEditable()) {
            throw ValidationException::withMessages([
                'status' => [__('aqdar.listings.not_editable')],
            ]);
        }

        return DB::transaction(function () use ($listing, $step, $data, $user): Listing {
            $wasLive = $listing->isPubliclyVisible();
            $before = $wasLive ? $this->fieldPayload($listing) : null;
            $this->applyStep($listing, $step, $data);
            $this->refreshContactFlag($listing);
            $listing = $listing->fresh([
                'financials',
                'installmentItems',
                'category',
                'governorate',
                'area',
                'developer',
                'project',
                'unitType',
                'fieldSnapshots',
                'statusHistory',
            ]) ?? $listing;

            if ($wasLive && $step !== 'documents' && $before !== $this->fieldPayload($listing)) {
                $listing = $this->moderation->returnToReview($listing, $user, 'published_fields_changed');
            }

            return $listing;
        });
    }

    public function submit(Listing $listing, User $user, bool $consent): Listing
    {
        if (! $consent) {
            throw ValidationException::withMessages([
                'consent' => [__('aqdar.listings.consent_required')],
            ]);
        }

        $this->assertReadyForReview($listing);
        $this->states->assertCanTransition($listing->status, ListingStatus::Submitted);

        $listing = DB::transaction(function () use ($listing, $user): Listing {
            $this->refreshContactFlag($listing);
            $listing->version = $listing->version + 1;
            $listing->consent_at = now();
            $listing->submitted_at = now();
            $listing->review_reference = $listing->public_code.'-R'.$listing->version;

            $this->transition($listing, ListingStatus::Submitted, $user, 'submitted_for_review');
            $this->states->assertCanTransition(ListingStatus::Submitted, ListingStatus::InReview);
            $this->transition($listing, ListingStatus::InReview, $user, $listing->contact_flagged
                ? 'held_contact_details:'.($listing->contact_flag_reason ?? 'flagged')
                : 'queued_for_review');

            $this->audit->write(
                $listing->contact_flagged ? 'listing.held_contact' : 'listing.submitted',
                $user,
                $listing,
                [
                    'reason' => $listing->contact_flag_reason,
                    'review_reference' => $listing->review_reference,
                ],
            );

            $this->snapshotFields($listing);

            return $listing->fresh(['financials', 'installmentItems', 'statusHistory', 'area', 'unitType', 'fieldSnapshots']) ?? $listing;
        });

        $this->notifyListingSubmitted($listing, $user);
        DeskRealtime::push('listings', 'submitted', (int) $listing->id, meta: [
            'public_code' => $listing->public_code,
        ]);

        return $listing;
    }

    public function resubmit(Listing $listing, User $user): Listing
    {
        $this->assertReadyForReview($listing);
        $this->states->assertCanTransition($listing->status, ListingStatus::Resubmitted);

        $listing = DB::transaction(function () use ($listing, $user): Listing {
            $this->refreshContactFlag($listing);
            $listing->version = $listing->version + 1;
            $listing->review_reference = $listing->public_code.'-R'.$listing->version;
            $listing->submitted_at = now();

            $this->transition($listing, ListingStatus::Resubmitted, $user, 'resubmitted');
            $this->states->assertCanTransition(ListingStatus::Resubmitted, ListingStatus::InReview);
            $this->transition($listing, ListingStatus::InReview, $user, $listing->contact_flagged
                ? 'held_contact_details:'.($listing->contact_flag_reason ?? 'flagged')
                : 'queued_for_review');

            $this->audit->write('listing.resubmitted', $user, $listing, [
                'review_reference' => $listing->review_reference,
            ]);

            $this->snapshotFields($listing);

            return $listing->fresh(['financials', 'installmentItems', 'statusHistory', 'fieldSnapshots', 'assignedReviewer']) ?? $listing;
        });

        $this->notifyStaffOfSubmission($listing);

        return $listing;
    }

    private function notifyListingSubmitted(Listing $listing, User $user): void
    {
        $context = [
            'listing_code' => $listing->public_code,
            'listing_id' => $listing->id,
            'area' => $listing->area?->localizedName(),
            'unit_type' => $listing->unitType?->localizedName(),
        ];
        $this->notifications->notifySafely($user, 'listing_submitted', $context);
        $this->notifyStaffOfSubmission($listing, $context);
    }

    /** @param  array<string, mixed>|null  $context */
    private function notifyStaffOfSubmission(Listing $listing, ?array $context = null): void
    {
        $context ??= [
            'listing_code' => $listing->public_code,
            'listing_id' => $listing->id,
            'area' => $listing->area?->localizedName(),
            'unit_type' => $listing->unitType?->localizedName(),
        ];
        $listing->loadMissing('assignedReviewer');
        $reviewer = $listing->assignedReviewer;
        $idempotency = 'staff_new_submission:listing:'.$listing->id.':v'.$listing->version;
        if ($reviewer !== null && $reviewer->is_active) {
            $this->notifications->notifySafely(
                $reviewer,
                'staff_new_submission',
                $context,
                $idempotency.':'.$reviewer->getMorphClass().':'.$reviewer->getKey(),
            );

            return;
        }
        $this->notifications->notifyStaffQueue('staff_new_submission', $context, Permission::ApproveListing, false, $idempotency);
    }

    /** @param array<string, mixed> $data */
    private function applyStep(Listing $listing, string $step, array $data): void
    {
        match ($step) {
            'category' => $this->applyCategory($listing, $data),
            'specs' => $this->applySpecs($listing, $data),
            'financials' => $this->applyFinancials($listing, $data),
            'media' => $listing->forceFill(['media_meta' => $data['media_meta'] ?? $data])->save(),
            'documents' => $listing->forceFill(['documents_meta' => $data['documents_meta'] ?? $data])->save(),
            'review' => null,
            default => throw ValidationException::withMessages([
                'step' => [__('aqdar.listings.unknown_step')],
            ]),
        };
    }

    /** @param array<string, mixed> $data */
    private function applyCategory(Listing $listing, array $data): void
    {
        if (! isset($data['category_id'])) {
            return;
        }

        $category = $this->activeCategory((int) $data['category_id']);
        $segment = $category->segment ?? ($category->parent?->segment);
        $child = $category->distressed_child;

        if (isset($data['distressed_child'])) {
            $child = DistressedChild::from($data['distressed_child']);
        }

        if ($segment === MarketSegment::Distressed && $child === null) {
            throw ValidationException::withMessages([
                'distressed_child' => [__('aqdar.listings.distressed_child_required')],
            ]);
        }

        if ($segment !== MarketSegment::Distressed) {
            $child = null;
        }

        $listing->fill([
            'category_id' => $category->id,
            'segment' => $segment,
            'distressed_child' => $child,
            'commission_policy_code' => $category->commission_policy_code,
        ])->save();
    }

    /** @param array<string, mixed> $data */
    private function applySpecs(Listing $listing, array $data): void
    {
        if (isset($data['governorate_id'])) {
            $this->assertActive(Governorate::class, (int) $data['governorate_id'], 'governorate_id');
        }

        if (isset($data['area_id'])) {
            $area = $this->assertActive(Area::class, (int) $data['area_id'], 'area_id');
            if (isset($data['governorate_id']) && (int) $area->governorate_id !== (int) $data['governorate_id']) {
                throw ValidationException::withMessages([
                    'area_id' => [__('aqdar.listings.area_mismatch')],
                ]);
            }
        }

        if (isset($data['developer_id'])) {
            $this->assertActive(Developer::class, (int) $data['developer_id'], 'developer_id');
        }

        if (isset($data['project_id'])) {
            $project = $this->assertActive(Project::class, (int) $data['project_id'], 'project_id');
            if (isset($data['developer_id']) && $project->developer_id !== null && (int) $project->developer_id !== (int) $data['developer_id']) {
                throw ValidationException::withMessages([
                    'project_id' => [__('aqdar.listings.project_mismatch')],
                ]);
            }
        }

        if (isset($data['unit_type_id'])) {
            $this->assertActive(UnitType::class, (int) $data['unit_type_id'], 'unit_type_id');
        }

        $listing->fill(collect($data)->only([
            'governorate_id',
            'area_id',
            'developer_id',
            'project_id',
            'unit_type_id',
            'title_ar',
            'title_en',
            'description_ar',
            'description_en',
            'area_sqm',
            'bedrooms',
            'bathrooms',
            'floor',
            'finishing',
            'furnished',
            'handover_date',
            'neighborhood',
            'phase',
            'unit_code',
            'parking',
            'lat',
            'lng',
            'amenity_ids',
            'tag_ids',
        ])->all())->save();
    }

    /** @param array<string, mixed> $data */
    private function applyFinancials(Listing $listing, array $data): void
    {
        $financials = $listing->financials ?? $listing->financials()->create([]);
        $declared = collect($data)->only([
            'declared_contract_price',
            'declared_contract_date',
            'declared_total_paid',
            'declared_remaining',
            'declared_overdue_count',
            'declared_overdue_amount',
            'declared_next_due_date',
            'declared_installment_amount',
            'declared_remaining_installments',
            'declared_payment_system',
            'declared_seller_recovery_amount',
            'declared_asking_price',
            'declared_cancellation_percent',
            'declared_assignment_restrictions',
        ])->all();

        if (array_key_exists('offer_expires_at', $data)) {
            $listing->forceFill(['offer_expires_at' => $data['offer_expires_at'] ?: null])->save();
        }

        $financials->fill($declared)->save();

        if (array_key_exists('installment_items', $data) && is_array($data['installment_items'])) {
            $this->assertInstallmentEvidence($listing, $data['installment_items']);
            $listing->installmentItems()->delete();
            foreach (array_values($data['installment_items']) as $index => $item) {
                InstallmentItem::query()->create([
                    'listing_id' => $listing->id,
                    'sequence' => $item['sequence'] ?? ($index + 1),
                    'due_date' => $item['due_date'] ?? null,
                    'amount' => $item['amount'],
                    'note' => $item['note'] ?? null,
                    'paid' => (bool) ($item['paid'] ?? false),
                    'evidence_document_id' => $item['evidence_document_id'] ?? null,
                ]);
            }
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function assertInstallmentEvidence(Listing $listing, array $items): void
    {
        $ids = collect($items)
            ->pluck('evidence_document_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $found = $listing->documents()->whereIn('id', $ids)->count();
        if ($found !== $ids->count()) {
            throw ValidationException::withMessages([
                'installment_items' => [__('aqdar.listings.installment_evidence_invalid')],
            ]);
        }
    }

    private function refreshContactFlag(Listing $listing): void
    {
        $reason = $this->scanner->scan(
            $listing->title_ar,
            $listing->title_en,
            $listing->description_ar,
            $listing->description_en,
        );

        $listing->forceFill([
            'contact_flagged' => $reason !== null,
            'contact_flag_reason' => $reason,
        ])->save();
    }

    private function assertReadyForReview(Listing $listing): void
    {
        $listing->loadMissing(['financials', 'category']);

        if ($listing->category_id === null || $listing->segment === null) {
            throw ValidationException::withMessages([
                'category_id' => [__('aqdar.listings.category_required')],
            ]);
        }

        if ($listing->segment === MarketSegment::Distressed && $listing->distressed_child === null) {
            throw ValidationException::withMessages([
                'distressed_child' => [__('aqdar.listings.distressed_child_required')],
            ]);
        }

        foreach (['governorate_id' => 'governorate_id', 'area_id' => 'area_id', 'unit_type_id' => 'unit_type_id'] as $attribute => $key) {
            if ($listing->{$attribute} === null) {
                throw ValidationException::withMessages([
                    $key => [__('aqdar.listings.specs_required')],
                ]);
            }
        }

        if (! filled($listing->title_ar) || ! filled($listing->description_ar)) {
            throw ValidationException::withMessages([
                'title_ar' => [__('aqdar.listings.specs_required')],
            ]);
        }

        if ($listing->area_sqm === null || (float) $listing->area_sqm <= 0) {
            throw ValidationException::withMessages([
                'area_sqm' => [__('aqdar.listings.area_positive')],
            ]);
        }

        if ($listing->bedrooms === null) {
            throw ValidationException::withMessages([
                'bedrooms' => [__('aqdar.listings.rooms_required')],
            ]);
        }

        $asking = $listing->financials?->declared_asking_price;
        $contract = $listing->financials?->declared_contract_price;

        if ($asking === null || $contract === null) {
            throw ValidationException::withMessages([
                'declared_asking_price' => [__('aqdar.listings.financials_required')],
            ]);
        }

        $this->assertActive(Category::class, (int) $listing->category_id, 'category_id');
        $this->assertActive(Governorate::class, (int) $listing->governorate_id, 'governorate_id');
        $this->assertActive(Area::class, (int) $listing->area_id, 'area_id');
        $this->assertActive(UnitType::class, (int) $listing->unit_type_id, 'unit_type_id');

        if ($listing->developer_id) {
            $this->assertActive(Developer::class, (int) $listing->developer_id, 'developer_id');
        }

        if ($listing->project_id) {
            $this->assertActive(Project::class, (int) $listing->project_id, 'project_id');
        }

        $this->files->assertRequiredDocumentsPresent($listing);
    }

    private function activeCategory(int $id): Category
    {
        /** @var Category $category */
        $category = $this->assertActive(Category::class, $id, 'category_id');
        $category->loadMissing('parent');

        return $category;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function assertActive(string $model, int $id, string $field): object
    {
        $record = $model::query()->find($id);

        if ($record === null || (property_exists($record, 'is_active') || isset($record->is_active)) && $record->is_active === false) {
            throw ValidationException::withMessages([
                $field => [__('aqdar.listings.retired_reference')],
            ]);
        }

        return $record;
    }

    private function transition(Listing $listing, ListingStatus $to, User $user, string $reason): void
    {
        $from = $listing->status;
        $listing->status = $to;
        $listing->save();
        $this->recordHistory($listing, $from, $to, $user, $reason);
    }

    private function recordHistory(Listing $listing, ?ListingStatus $from, ListingStatus $to, User $user, string $reason): void
    {
        ListingStatusHistory::query()->create([
            'listing_id' => $listing->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $user->getMorphClass(),
            'actor_id' => $user->getKey(),
            'reason' => $reason,
        ]);
    }

    private function snapshotFields(Listing $listing): void
    {
        $listing->loadMissing(['financials', 'installmentItems']);

        ListingFieldSnapshot::query()->updateOrCreate(
            [
                'listing_id' => $listing->id,
                'version' => (int) $listing->version,
            ],
            ['payload' => $this->fieldPayload($listing)],
        );
    }

    /** @return array<string, mixed> */
    private function fieldPayload(Listing $listing): array
    {
        $listing->loadMissing(['financials', 'installmentItems']);
        $financials = $listing->financials;

        return [
            'title_ar' => $listing->title_ar,
            'title_en' => $listing->title_en,
            'description_ar' => $listing->description_ar,
            'description_en' => $listing->description_en,
            'category_id' => $listing->category_id,
            'segment' => $listing->segment?->value,
            'distressed_child' => $listing->distressed_child?->value,
            'governorate_id' => $listing->governorate_id,
            'area_id' => $listing->area_id,
            'developer_id' => $listing->developer_id,
            'project_id' => $listing->project_id,
            'unit_type_id' => $listing->unit_type_id,
            'area_sqm' => $listing->area_sqm !== null ? (string) $listing->area_sqm : null,
            'bedrooms' => $listing->bedrooms,
            'bathrooms' => $listing->bathrooms,
            'floor' => $listing->floor,
            'finishing' => $listing->finishing,
            'furnished' => $listing->furnished,
            'handover_date' => $listing->handover_date?->toDateString(),
            'neighborhood' => $listing->neighborhood,
            'phase' => $listing->phase,
            'unit_code' => $listing->unit_code,
            'parking' => $listing->parking,
            'offer_expires_at' => $listing->offer_expires_at?->toIso8601String(),
            'commission_policy_code' => $listing->commission_policy_code,
            'lat' => $listing->lat !== null ? (string) $listing->lat : null,
            'lng' => $listing->lng !== null ? (string) $listing->lng : null,
            'amenity_ids' => $listing->amenity_ids ?? [],
            'tag_ids' => $listing->tag_ids ?? [],
            'media_meta' => $listing->media_meta ?? [],
            'financials' => [
                'declared_contract_price' => $financials?->declared_contract_price !== null ? (string) $financials->declared_contract_price : null,
                'declared_asking_price' => $financials?->declared_asking_price !== null ? (string) $financials->declared_asking_price : null,
                'declared_total_paid' => $financials?->declared_total_paid !== null ? (string) $financials->declared_total_paid : null,
                'declared_remaining' => $financials?->declared_remaining !== null ? (string) $financials->declared_remaining : null,
                'declared_cancellation_percent' => $financials?->declared_cancellation_percent !== null ? (string) $financials->declared_cancellation_percent : null,
            ],
            'installment_items' => $listing->installmentItems->map(fn ($item) => [
                'sequence' => $item->sequence,
                'due_date' => $item->due_date,
                'amount' => $item->amount !== null ? (string) $item->amount : null,
                'note' => $item->note,
                'paid' => (bool) $item->paid,
                'evidence_document_id' => $item->evidence_document_id,
            ])->values()->all(),
        ];
    }
}
