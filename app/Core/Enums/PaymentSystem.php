<?php

namespace App\Core\Enums;

enum PaymentSystem: string
{
    case Cash = 'cash';
    case Installment = 'installment';
    case Mix = 'mix';
}
