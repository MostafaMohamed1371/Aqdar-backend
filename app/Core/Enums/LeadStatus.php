<?php

namespace App\Core\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case VisitScheduled = 'visit_scheduled';
    case OfferSent = 'offer_sent';
    case Converted = 'converted';
    case Lost = 'lost';

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Converted, self::Lost], true);
    }
}
