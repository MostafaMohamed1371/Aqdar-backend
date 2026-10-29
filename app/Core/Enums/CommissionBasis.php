<?php

namespace App\Core\Enums;

enum CommissionBasis: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
