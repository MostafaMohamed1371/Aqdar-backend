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
}
