<?php

namespace App\Modules\Crm\Services\Ocr;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\ExtractionStatus;
use App\Modules\Crm\Models\CaseDocument;
use App\Modules\Crm\Models\DocumentExtraction;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentExtractionService
{
    public function __construct(
        private DocumentExtractor $extractor,
        private AuditLogger $audit,
    ) {}

    public function queue(CaseDocument $document): DocumentExtraction
    {
        return DocumentExtraction::query()->firstOrCreate(
            ['case_document_id' => $document->id],
            [
                'driver' => (string) config('aqdar.ocr.driver', 'manual'),
                'status' => ExtractionStatus::PendingReview,
                'fields' => $this->suggestions($document),
            ],
        );
    }

    /** @param array<string, mixed> $fields */
    public function review(DocumentExtraction $extraction, StaffUser $actor, ExtractionStatus $status, array $fields = []): DocumentExtraction
    {
        $confirmed = $status === ExtractionStatus::Confirmed ? $this->allowedFields($fields) : null;

        $extraction->forceFill([
            'status' => $status,
            'fields' => $confirmed,
            'reviewed_by_staff_id' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit->write('case.document_reviewed', $actor, $extraction->document, [
            'status' => $status->value,
            'fields' => array_keys($confirmed ?? []),
        ]);

        return $extraction->fresh() ?? $extraction;
    }

    /** @return array<string, string>|null */
    private function suggestions(CaseDocument $document): ?array
    {
        try {
            $suggested = $this->allowedFields($this->extractor->extract($document));
        } catch (\Throwable $exception) {
            Log::warning('document_extraction.failed', ['document_id' => $document->id, 'error' => $exception->getMessage()]);

            return null;
        }

        return $suggested === [] ? null : $suggested;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    private function allowedFields(array $fields): array
    {
        $clean = [];
        foreach ((array) config('aqdar.ocr.fields', []) as $key) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value !== '') {
                $clean[$key] = Str::limit($value, 255, '');
            }
        }

        return $clean;
    }
}
