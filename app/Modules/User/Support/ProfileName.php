<?php

namespace App\Modules\User\Support;

final class ProfileName
{
    /**
     * OTP seeds look like "مستخدم 1234" / "User 1234" and must not be re-saved as a real name.
     */
    public static function isPlaceholder(?string $name): bool
    {
        if ($name === null) {
            return true;
        }

        $trimmed = self::normalize($name);

        if ($trimmed === '') {
            return true;
        }

        if (preg_match('/^مستخدم(?:\s*[\d٠-٩]+)?$/u', $trimmed) === 1) {
            return true;
        }

        return preg_match('/^user(?:\s*\d+)?$/iu', $trimmed) === 1;
    }

    public static function isTooShort(?string $name): bool
    {
        return mb_strlen(self::normalize((string) $name)) < 3;
    }

    public static function isAcceptable(?string $name): bool
    {
        return ! self::isPlaceholder($name) && ! self::isTooShort($name);
    }

    public static function seedForPhone(string $phone): string
    {
        return 'مستخدم '.substr($phone, -4);
    }

    private static function normalize(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    }
}
