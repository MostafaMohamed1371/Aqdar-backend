<?php

namespace App\Modules\Crm\Services\Ocr;

use App\Modules\Crm\Models\CaseDocument;

interface DocumentExtractor
{
    /**
     * Reads suggested values from an uploaded document. Keys must be a subset of config('aqdar.ocr.fields').
     * Suggestions are never trusted: staff confirm or reject them before they are used.
     *
     * @return array<string, string>
     */
    public function extract(CaseDocument $document): array;
}
