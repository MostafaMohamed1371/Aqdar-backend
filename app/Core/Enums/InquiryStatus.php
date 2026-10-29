<?php

namespace App\Core\Enums;

enum InquiryStatus: string
{
    case New = 'new';
    case InReview = 'in_review';
    case Replied = 'replied';
    case Closed = 'closed';
}
