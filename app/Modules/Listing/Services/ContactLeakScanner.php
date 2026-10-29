<?php

namespace App\Modules\Listing\Services;

class ContactLeakScanner
{
    public function scan(?string ...$texts): ?string
    {
        $haystack = implode("\n", array_filter($texts, fn ($text) => is_string($text) && $text !== ''));

        if ($haystack === '') {
            return null;
        }

        if (preg_match('/(?:\+?20)?0?1[0125]\d{8}/u', $haystack) === 1) {
            return 'phone';
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $haystack) === 1) {
            return 'email';
        }

        if (preg_match('/https?:\/\/|www\.|wa\.me\/|whatsapp|واتساب/i', $haystack) === 1) {
            return 'url';
        }

        if (preg_match('/\bqr(?:[\s\-]*code)?\b|باركود|كيو\s*آر/iu', $haystack) === 1) {
            return 'qr';
        }

        return null;
    }
}
