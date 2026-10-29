<?php

namespace App\Core\Identity;

use InvalidArgumentException;

class EgyptianPhone
{
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '0020')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '20') && strlen($digits) >= 11) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (! preg_match('/^[1-9]\d{8,9}$/', $digits)) {
            throw new InvalidArgumentException('Invalid Egyptian phone number.');
        }

        return '+20'.$digits;
    }

    public static function isValid(string $raw): bool
    {
        try {
            self::normalize($raw);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function toNational(string $e164): string
    {
        $normalized = self::normalize($e164);

        return '0'.substr($normalized, 3);
    }
}
