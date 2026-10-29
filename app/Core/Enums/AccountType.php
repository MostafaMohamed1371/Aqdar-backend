<?php

namespace App\Core\Enums;

enum AccountType: string
{
    case Individual = 'individual';
    case Developer = 'developer';
    case Broker = 'broker';

    public function labelAr(): string
    {
        return match ($this) {
            self::Individual => 'فرد',
            self::Developer => 'مطور',
            self::Broker => 'وسيط',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Developer => 'Developer',
            self::Broker => 'Broker',
        };
    }
}
