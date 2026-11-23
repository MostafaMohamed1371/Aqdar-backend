<?php

namespace Tests\Integration;

use App\Core\Enums\CaseDocumentKind;
use App\Core\Enums\ExtractionStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\CaseDocument;
use App\Modules\Crm\Models\DocumentExtraction;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Crm\Services\CaseService;
use App\Modules\Crm\Services\Ocr\DocumentExtractor;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DocumentExtractionTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    private function uploadDocument(TransferCase $case, StaffUser $staff): CaseDocument
    {
        return app(CaseService::class)->storeDocument(
            $case,
            UploadedFile::fake()->create('deed.pdf', 40, 'application/pdf'),
            CaseDocumentKind::DeveloperApproval,
            null,
            $staff,
        );
    }

    public function test_an_uploaded_document_is_queued_for_review_with_manual_driver(): void
    {
        Storage::fake('private_documents');
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin);
        $document = $this->uploadDocument(TransferCase::factory()->create(), $staff);

        $extraction = DocumentExtraction::query()->where('case_document_id', $document->id)->firstOrFail();
        $this->assertSame(ExtractionStatus::PendingReview, $extraction->status);
        $this->assertSame('manual', $extraction->driver);
        $this->assertNull($extraction->fields);
    }

    public function test_suggestions_from_a_reader_are_kept_only_for_allowed_fields(): void
    {
        Storage::fake('private_documents');
        $this->app->instance(DocumentExtractor::class, new class implements DocumentExtractor
        {
            public function extract(CaseDocument $document): array
            {
                return ['full_name' => 'Ahmed Ali', 'national_id' => '29901011234567', 'shoe_size' => '42'];
            }
        });
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin);
        $document = $this->uploadDocument(TransferCase::factory()->create(), $staff);

        $this->assertSame(
            ['full_name' => 'Ahmed Ali', 'national_id' => '29901011234567'],
            DocumentExtraction::query()->where('case_document_id', $document->id)->firstOrFail()->fields,
        );
    }

    public function test_a_failing_reader_does_not_block_the_upload(): void
    {
        Storage::fake('private_documents');
        $this->app->instance(DocumentExtractor::class, new class implements DocumentExtractor
        {
            public function extract(CaseDocument $document): array
            {
                throw new \RuntimeException('ocr timeout');
            }
        });
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin);
        $document = $this->uploadDocument(TransferCase::factory()->create(), $staff);

        $extraction = DocumentExtraction::query()->where('case_document_id', $document->id)->firstOrFail();
        $this->assertSame(ExtractionStatus::PendingReview, $extraction->status);
        $this->assertNull($extraction->fields);
    }

    public function test_staff_confirm_keeps_only_the_configured_fields(): void
    {
        Storage::fake('private_documents');
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin);
        $document = $this->uploadDocument(TransferCase::factory()->create(), $staff);

        $this->post(route('dashboard.cases.documents.extraction', $document), [
            'status' => 'confirmed',
            'fields' => ['national_id' => ' 29901011234567 ', 'full_name' => 'Ahmed Ali', 'shoe_size' => '42'],
        ])->assertSessionHas('status');

        $extraction = DocumentExtraction::query()->where('case_document_id', $document->id)->firstOrFail();
        $this->assertSame(ExtractionStatus::Confirmed, $extraction->status);
        $this->assertEqualsCanonicalizing(['national_id' => '29901011234567', 'full_name' => 'Ahmed Ali'], $extraction->fields);
        $this->assertSame($staff->id, $extraction->reviewed_by_staff_id);
        $this->assertNotNull($extraction->reviewed_at);
    }

    public function test_staff_reject_clears_the_suggested_values(): void
    {
        Storage::fake('private_documents');
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin);
        $document = $this->uploadDocument(TransferCase::factory()->create(), $staff);

        $this->post(route('dashboard.cases.documents.extraction', $document), [
            'status' => 'rejected',
            'fields' => ['national_id' => '29901011234567'],
        ]);

        $extraction = DocumentExtraction::query()->where('case_document_id', $document->id)->firstOrFail();
        $this->assertSame(ExtractionStatus::Rejected, $extraction->status);
        $this->assertNull($extraction->fields);
    }

    public function test_finance_only_staff_cannot_review_documents(): void
    {
        Storage::fake('private_documents');
        $admin = $this->actingAsStaff(StaffRole::SuperAdmin);
        $document = $this->uploadDocument(TransferCase::factory()->create(), $admin);

        $this->actingAsStaff(StaffRole::Finance);

        $this->post(route('dashboard.cases.documents.extraction', $document), ['status' => 'confirmed'])
            ->assertForbidden();
    }
}
