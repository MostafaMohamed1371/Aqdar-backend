<?php

namespace App\Core\Enums;

enum BuyerRequestStatus: string
{
    case New = 'new';
    case Assigned = 'assigned';
    case ContactingBuyer = 'contacting_buyer';
    case Matching = 'matching';
    case OptionsShared = 'options_shared';
    case NegotiationVisit = 'negotiation_visit';
    case Converted = 'converted';
    case ClosedNoMatch = 'closed_no_match';

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Converted, self::ClosedNoMatch], true);
    }

    public function clientStatus(): string
    {
        return match ($this) {
            self::New, self::Assigned, self::ContactingBuyer, self::Matching => 'in_review',
            self::OptionsShared, self::NegotiationVisit => 'replied',
            self::Converted, self::ClosedNoMatch => 'closed',
        };
    }
}
