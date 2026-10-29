<?php

namespace App\Core\Enums;

enum CaseStatus: string
{
    case Open = 'open';
    case WaitingForInformation = 'waiting_for_information';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';
    case Lost = 'lost';
    case Completed = 'completed';

    public function isClosed(): bool
    {
        return in_array($this, [self::Cancelled, self::Lost, self::Completed], true);
    }
}
