<?php

namespace App\Core\Enums;

enum LeadSource: string
{
    case ListingInquiry = 'listing_inquiry';
    case CustomRequest = 'custom_request';
    case Campaign = 'campaign';
    case Referral = 'referral';
}
