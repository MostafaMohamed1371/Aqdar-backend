<?php

namespace App\Core\Enums;

enum CommissionStatus: string
{
    case Earned = 'earned';
    case Due = 'due';
    case Paid = 'paid';
    case Waived = 'waived';

    public function isRecorded(): bool
    {
        return in_array($this, [self::Earned, self::Due, self::Paid], true);
    }
}
