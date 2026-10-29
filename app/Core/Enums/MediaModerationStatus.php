<?php

namespace App\Core\Enums;

enum MediaModerationStatus: string
{
    case Pending = 'pending';
    case Held = 'held';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
