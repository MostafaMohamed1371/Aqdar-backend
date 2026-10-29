<?php

namespace App\Core\Enums;

enum CommissionPayer: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case Both = 'both';
}
