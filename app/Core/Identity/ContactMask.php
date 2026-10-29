<?php

namespace App\Core\Identity;

class ContactMask
{
    public static function email(?string $email): string
    {
        if ($email === null || $email === '') {
            return '—';
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return '***';
        }

        $visible = mb_substr($local, 0, 1);

        return $visible.'***@'.$domain;
    }

    public static function phone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '—';
        }

        try {
            $national = EgyptianPhone::toNational($phone);
        } catch (\InvalidArgumentException) {
            $national = preg_replace('/\D+/', '', $phone) ?? '';
        }

        $length = strlen($national);

        if ($length < 7) {
            return '****';
        }

        $prefix = substr($national, 0, 3);
        $suffix = substr($national, -4);

        return $prefix.'****'.$suffix;
    }
}
