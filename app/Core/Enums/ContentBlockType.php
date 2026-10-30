<?php

namespace App\Core\Enums;

enum ContentBlockType: string
{
    case Banner = 'banner';
    case Faq = 'faq';
    case Support = 'support';
    case Onboarding = 'onboarding';
    case SellerGuide = 'seller_guide';
}
