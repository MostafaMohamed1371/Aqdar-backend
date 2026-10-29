<?php

namespace App\Core\Enums;

enum DocumentReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ReplacementRequested = 'replacement_requested';
}
