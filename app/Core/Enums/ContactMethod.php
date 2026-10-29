<?php

namespace App\Core\Enums;

enum ContactMethod: string
{
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
}
