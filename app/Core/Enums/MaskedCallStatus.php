<?php

namespace App\Core\Enums;

enum MaskedCallStatus: string
{
    case PendingProvider = 'pending_provider';
    case Connected = 'connected';
    case Failed = 'failed';
}
