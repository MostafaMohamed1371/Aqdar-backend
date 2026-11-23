<?php

namespace App\Core\Enums;

enum EscrowMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Cheque = 'cheque';
}
