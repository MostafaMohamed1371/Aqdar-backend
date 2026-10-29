<?php

namespace App\Core\Enums;

enum IdentityStatus: string
{
    case Unverified = 'unverified';
    case Pending = 'pending';
    case Verified = 'verified';

    public function labelAr(): string
    {
        return match ($this) {
            self::Unverified => 'غير موثق',
            self::Pending => 'قيد التحقق',
            self::Verified => 'موثق',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Unverified => 'bad',
            self::Pending => 'gold',
            self::Verified => 'ok',
        };
    }
}
