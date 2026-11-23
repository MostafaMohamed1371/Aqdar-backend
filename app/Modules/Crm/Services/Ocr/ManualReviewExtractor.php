<?php

namespace App\Modules\Crm\Services\Ocr;

use App\Modules\Crm\Models\CaseDocument;

/**
 * Default driver: no automatic reading. Staff type the values during review.
 */
class ManualReviewExtractor implements DocumentExtractor
{
    public function extract(CaseDocument $document): array
    {
        return [];
    }
}
