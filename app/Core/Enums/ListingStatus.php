<?php

namespace App\Core\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case NeedsChanges = 'needs_changes';
    case Resubmitted = 'resubmitted';
    case Approved = 'approved';
    case Published = 'published';
    case UnderOffer = 'under_offer';
    case Hold = 'hold';
    case Closed = 'closed';
    case Archived = 'archived';
    case Rejected = 'rejected';

    public function isPublic(): bool
    {
        return $this === self::Published || $this === self::UnderOffer;
    }

    /** @return list<self> */
    public static function publicCases(): array
    {
        return [self::Published, self::UnderOffer];
    }
}
