<?php

namespace App\Core\Enums;

enum MatchBuyerResponse: string
{
    case Interested = 'interested';
    case Waiting = 'waiting';
    case Declined = 'declined';
}
