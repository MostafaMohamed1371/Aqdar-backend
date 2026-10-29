<?php

namespace App\Core\Enums;

enum PublicCodePrefix: string
{
    case Listing = 'AQ';
    case Inquiry = 'INQ';
    case BuyerRequest = 'BR';
    case Lead = 'LD';
    case Transfer = 'TR';
    case Appointment = 'APT';
    case Support = 'SUP';

    public function usesYear(): bool
    {
        return $this !== self::Listing;
    }
}
