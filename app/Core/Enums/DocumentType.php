<?php

namespace App\Core\Enums;

enum DocumentType: string
{
    case NationalId = 'national_id';
    case Contract = 'contract';
    case PaymentProof = 'payment_proof';
    case DeveloperStatusLetter = 'developer_status_letter';

    public function isRequiredFor(?MarketSegment $segment): bool
    {
        $config = config('aqdar.files.documents.types.'.$this->value, []);
        $required = (bool) ($config['required'] ?? false);
        $segments = $config['segments'] ?? null;

        if (is_array($segments) && $segments !== []) {
            return $required && $segment !== null && in_array($segment->value, $segments, true);
        }

        return $required;
    }

    public function label(?string $locale = null): string
    {
        return __('aqdar.listings.doc_type.'.$this->value, locale: $locale);
    }

    /**
     * @param  list<self>  $types
     */
    public static function labeledList(array $types, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $labels = array_map(
            static fn (self $type): string => $type->label($locale),
            $types,
        );
        $separator = $locale === 'ar' ? '، ' : ', ';

        return implode($separator, $labels);
    }
}
