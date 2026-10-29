<?php

namespace App\Core\Enums;

enum NotificationCategory: string
{
    case Listing = 'listing';
    case Inquiry = 'inquiry';
    case Case = 'case';
    case System = 'system';
    case Marketing = 'marketing';
    case Smart = 'smart';
}
