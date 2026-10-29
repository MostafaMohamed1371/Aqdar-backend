<?php

namespace App\Core\Enums;

enum LeadActivityType: string
{
    case Call = 'call';
    case Note = 'note';
    case Task = 'task';
}
