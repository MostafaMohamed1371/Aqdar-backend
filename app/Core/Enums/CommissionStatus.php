<?php

namespace App\Core\Enums;

enum CommissionStatus: string
{
    case Earned = 'earned';
    case Due = 'due';
    case Paid = 'paid';
    case Waived = 'waived';
    case Reversed = 'reversed';

    public function isRecorded(): bool
    {
        return in_array($this, [self::Earned, self::Due, self::Paid], true);
    }

    /** @return list<string> */
    public static function excludedFromRevenue(): array
    {
        return [self::Waived->value, self::Reversed->value];
    }
}
