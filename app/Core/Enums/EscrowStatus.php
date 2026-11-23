<?php

namespace App\Core\Enums;

enum EscrowStatus: string
{
    case Held = 'held';
    case Released = 'released';
    case Refunded = 'refunded';

    public function isSettled(): bool
    {
        return $this !== self::Held;
    }
}
