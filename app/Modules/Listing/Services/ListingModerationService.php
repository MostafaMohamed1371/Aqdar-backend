<?php

namespace App\Modules\Listing\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\Permission;
use App\Modules\Dashboard\Support\DeskLiveRow;
use App\Modules\Dashboard\Support\DeskRealtime;
use App\Modules\Listing\Jobs\MatchSavedSearchesJob;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingStatusHistory;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListingModerationService
{
    public function __construct(
        private ListingStateMachine $states,
        private AuditLogger $audit,
        private NotificationService $notifications,
    ) {}

    public function approve(Listing $listing, StaffUser $staff, string $reason): Listing
    {
        $this->assertDocumentsApproved($listing);

        return $this->move($listing, ListingStatus::Approved, $staff, $reason, 'listing.approved');
    }

    /** @param array<string, string> $fieldNotes section => note shown to the seller */
    public function requestChanges(Listing $listing, StaffUser $staff, string $reason, array $fieldNotes = []): Listing
    {
        $fieldNotes = collect($fieldNotes)
            ->only(ListingStatusHistory::NOTE_SECTIONS)
            ->map(fn ($note) => trim((string) $note))
            ->filter()
            ->all();

        return $this->move($listing, ListingStatus::NeedsChanges, $staff, $reason, 'listing.needs_changes', $fieldNotes);
    }

    public function reject(Listing $listing, StaffUser $staff, string $reason): Listing
    {
        return $this->move($listing, ListingStatus::Rejected, $staff, $reason, 'listing.rejected');
    }

    public function reopen(Listing $listing, StaffUser $staff, string $reason): Listing
    {
        return $this->move($listing, ListingStatus::InReview, $staff, $reason, 'listing.reopened');
    }

    public function publish(Listing $listing, StaffUser $staff, string $reason): Listing
    {
        $this->assertDocumentsApproved($listing);
        // Ensure any already-approved photos have a public_path before going live.
        app(ListingFileService::class)->repairApprovedPublicDerivatives((int) $listing->id);
        $listing = $this->move($listing, ListingStatus::Published, $staff, $reason, 'listing.published');
        $listing->forceFill(['published_at' => $listing->published_at ?? now()])->save();
        MatchSavedSearchesJob::dispatch($listing->id);

        return $listing->fresh(['financials']) ?? $listing;
    }

    public function unpublish(Listing $listing, StaffUser $staff, string $reason): Listing
    {
        return $this->move($listing, ListingStatus::Approved, $staff, $reason, 'listing.unpublished');
    }

    public function markUnderOffer(Listing $listing, StaffUser $staff, string $reason): Listing
    {
        return $this->move($listing, ListingStatus::UnderOffer, $staff, $reason, 'listing.under_offer');
    }

    public function hold(Listing $listing, StaffUser $staff, string $reason): Listing
    {
        return $this->move($listing, ListingStatus::Hold, $staff, $reason, 'listing.held');
    }

    public function close(Listing $listing, StaffUser $staff, string $reason): Listing
    {
        return $this->move($listing, ListingStatus::Closed, $staff, $reason, 'listing.closed');
    }

    public function archive(Listing $listing, StaffUser $staff, string $reason): Listing
    {
        return $this->move($listing, ListingStatus::Archived, $staff, $reason, 'listing.archived');
    }

    public function assignReviewer(Listing $listing, StaffUser $staff, ?int $reviewerId, ?string $reason = null): Listing
    {
        $reviewer = null;
        if ($reviewerId !== null) {
            $reviewer = StaffUser::query()->find($reviewerId);
            if ($reviewer === null || ! $reviewer->is_active || ! $reviewer->hasPermission(Permission::ApproveListing)) {
                throw ValidationException::withMessages([
                    'assigned_reviewer_id' => [__('aqdar.listings.desk.reviewer_invalid')],
                ]);
            }
        }

        $current = $listing->assigned_reviewer_id;
        if ($current !== null && $current !== $reviewer?->id && mb_strlen(trim((string) $reason)) < 3) {
            throw ValidationException::withMessages([
                'reason' => [__('aqdar.crm.reassign_reason_required')],
            ]);
        }

        $listing->forceFill(['assigned_reviewer_id' => $reviewer?->id])->save();
        $this->audit->write('listing.reviewer_assigned', $staff, $listing, array_filter([
            'assigned_reviewer_id' => $reviewer?->id,
            'previous_reviewer_id' => $current,
            'reason' => $reason,
        ]));

        return $listing->fresh(['assignedReviewer']) ?? $listing;
    }

    public function returnToReview(Listing $listing, User|StaffUser $actor, string $reason): Listing
    {
        if (! $listing->isPubliclyVisible()) {
            return $listing;
        }

        return $this->move($listing, ListingStatus::InReview, $actor, $reason, 'listing.returned_to_review');
    }

    /** @param array<string, mixed> $verified */
    public function verifyFinancials(Listing $listing, StaffUser $staff, array $verified): Listing
    {
        $financials = $listing->financials ?? $listing->financials()->create([]);
        $allowed = [
            'contract_price',
            'total_paid',
            'remaining',
            'overdue_count',
            'overdue_amount',
            'next_due_date',
            'installment_amount',
            'remaining_installments',
            'seller_recovery_amount',
            'asking_price',
            'cancellation_percent',
        ];

        $payload = [];
        foreach ($allowed as $field) {
            $key = 'verified_'.$field;
            if (! array_key_exists($key, $verified) || $verified[$key] === null || $verified[$key] === '') {
                continue;
            }
            $payload[$key] = $verified[$key];
            $flag = $field === 'seller_recovery_amount' ? 'seller_recovery_verified' : $field.'_verified';
            $payload[$flag] = true;
        }

        if ($payload === []) {
            throw ValidationException::withMessages([
                'verified' => [__('aqdar.listings.desk.verify_required')],
            ]);
        }

        $listing->loadMissing('financials');
        $before = $listing->isPubliclyVisible() ? $listing->publicAskingPrice() : null;
        $financials->fill($payload)->save();

        $this->audit->write('listing.financials_verified', $staff, $listing, [
            'verified' => array_keys($payload),
            'declared_preserved' => true,
            'reason' => $verified['reason'] ?? null,
        ]);

        $fresh = $listing->fresh(['financials']) ?? $listing;
        $after = $fresh->publicAskingPrice();
        if ($fresh->isPubliclyVisible() && $before !== null && $after !== null && (float) $after < (float) $before) {
            MatchSavedSearchesJob::dispatch($fresh->id, 'price_drop');
        }

        return $fresh;
    }

    private function assertDocumentsApproved(Listing $listing): void
    {
        app(ListingFileService::class)->assertRequiredDocumentsApproved($listing);
    }

    /** @param array<string, string> $fieldNotes */
    private function move(Listing $listing, ListingStatus $to, Model $actor, string $reason, string $action, array $fieldNotes = []): Listing
    {
        $this->states->assertCanTransition($listing->status, $to);

        return DB::transaction(function () use ($listing, $to, $actor, $reason, $action, $fieldNotes): Listing {
            $from = $listing->status;
            $listing->status = $to;
            $listing->save();

            $history = ListingStatusHistory::query()->create([
                'listing_id' => $listing->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_type' => $actor->getMorphClass(),
                'actor_id' => $actor->getKey(),
                'reason' => $reason,
                'field_notes' => $fieldNotes === [] ? null : $fieldNotes,
            ]);

            $this->audit->write($action, $actor, $listing, array_filter([
                'from' => $from?->value,
                'to' => $to->value,
                'reason' => $reason,
                'field_notes' => $fieldNotes === [] ? null : array_keys($fieldNotes),
            ], fn ($value) => $value !== null));

            $fresh = $listing->fresh(['financials', 'owner', 'area', 'unitType']) ?? $listing;
            $event = match ($action) {
                'listing.approved' => 'listing_approved',
                'listing.reopened', 'listing.returned_to_review' => 'listing_submitted',
                'listing.needs_changes' => 'listing_needs_changes',
                'listing.published' => 'listing_published',
                'listing.rejected' => 'listing_rejected',
                'listing.held' => 'listing_held',
                'listing.under_offer' => 'listing_under_offer',
                'listing.closed' => 'listing_closed',
                'listing.unpublished', 'listing.archived' => 'listing_suspended',
                default => null,
            };
            if ($event !== null && $fresh->owner !== null) {
                $this->notifications->notifySafely($fresh->owner, $event, [
                    'listing_code' => $fresh->public_code,
                    'listing_id' => $fresh->id,
                    'area' => $fresh->area?->localizedName(),
                    'unit_type' => $fresh->unitType?->localizedName(),
                    'status' => $to->value,
                ], $event.':listing:'.$fresh->id.':history:'.$history->id);
            }

            $fresh->loadMissing(['governorate', 'developer']);
            $deskAction = match ($action) {
                'listing.returned_to_review', 'listing.reopened' => 'submitted',
                default => $action,
            };
            DeskRealtime::push('listings', $deskAction, (int) $fresh->id, meta: [
                'public_code' => $fresh->public_code,
                'status' => $to->value,
                'row' => DeskLiveRow::listing($fresh),
            ]);

            return $fresh;
        });
    }
}
