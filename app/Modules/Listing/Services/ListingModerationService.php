<?php

namespace App\Modules\Listing\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\Permission;
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

    public function requestChanges(Listing $listing, StaffUser $staff, string $reason): Listing
    {
        return $this->move($listing, ListingStatus::NeedsChanges, $staff, $reason, 'listing.needs_changes');
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
        $listing = $this->move($listing, ListingStatus::Published, $staff, $reason, 'listing.published');
        $listing->forceFill(['published_at' => $listing->published_at ?? now()])->save();

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

    public function assignReviewer(Listing $listing, StaffUser $staff, ?int $reviewerId): Listing
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

        $listing->forceFill(['assigned_reviewer_id' => $reviewer?->id])->save();
        $this->audit->write('listing.reviewer_assigned', $staff, $listing, [
            'assigned_reviewer_id' => $reviewer?->id,
        ]);

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

        $financials->fill($payload)->save();

        $this->audit->write('listing.financials_verified', $staff, $listing, [
            'verified' => array_keys($payload),
            'declared_preserved' => true,
        ]);

        return $listing->fresh(['financials']) ?? $listing;
    }

    private function assertDocumentsApproved(Listing $listing): void
    {
        app(ListingFileService::class)->assertRequiredDocumentsApproved($listing);
    }

    private function move(Listing $listing, ListingStatus $to, Model $actor, string $reason, string $action): Listing
    {
        $this->states->assertCanTransition($listing->status, $to);

        return DB::transaction(function () use ($listing, $to, $actor, $reason, $action): Listing {
            $from = $listing->status;
            $listing->status = $to;
            $listing->save();

            ListingStatusHistory::query()->create([
                'listing_id' => $listing->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_type' => $actor->getMorphClass(),
                'actor_id' => $actor->getKey(),
                'reason' => $reason,
            ]);

            $this->audit->write($action, $actor, $listing, [
                'from' => $from?->value,
                'to' => $to->value,
                'reason' => $reason,
            ]);

            $fresh = $listing->fresh(['financials', 'owner', 'area', 'unitType']) ?? $listing;
            $event = match ($action) {
                'listing.needs_changes' => 'listing_needs_changes',
                'listing.published' => 'listing_published',
                'listing.rejected' => 'listing_rejected',
                'listing.held' => 'listing_held',
                'listing.unpublished', 'listing.archived' => 'listing_suspended',
                default => null,
            };
            if ($event !== null && $fresh->owner !== null) {
                $this->notifications->notifySafely($fresh->owner, $event, [
                    'listing_code' => $fresh->public_code,
                    'listing_id' => $fresh->id,
                    'area' => $fresh->area?->localizedName(),
                    'unit_type' => $fresh->unitType?->localizedName(),
                ]);
            }

            return $fresh;
        });
    }
}
