<?php

namespace App\Core\Enums;

enum ExtractionStatus: string
{
    case PendingReview = 'pending_review';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
