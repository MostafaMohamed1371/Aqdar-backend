<?php

namespace Tests\Integration;

use App\Core\Enums\DocumentReviewStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use App\Modules\Listing\Services\PdfWatermarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardDocumentsTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_documents');
    }

    public function test_reviewer_can_open_adm_03_and_approve_document(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create(['public_code' => 'AQ-4401']);
        $document = ListingDocument::factory()->create(['listing_id' => $listing->id]);

        $this->get('/dashboard/documents')
            ->assertOk()
            ->assertSee('AQ-4401', false)
            ->assertSee(__('aqdar.listings.docs.signed_note'));

        $this->get('/dashboard/documents/listing/'.$listing->id)
            ->assertOk()
            ->assertSee(__('aqdar.listings.doc_type.national_id'))
            ->assertSee(__('aqdar.listings.docs.open'));

        $this->from('/dashboard/documents/listing/'.$listing->id)
            ->post('/dashboard/documents/'.$document->id.'/approve', ['reason' => 'واضح ومقروء'])
            ->assertRedirect();

        $this->assertSame(DocumentReviewStatus::Approved, $document->fresh()->review_status);
    }

    public function test_reviewer_can_reject_document_and_see_versions(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create(['public_code' => 'AQ-4402', 'segment' => \App\Core\Enums\MarketSegment::NonDistressed]);
        ListingDocument::factory()->create([
            'listing_id' => $listing->id,
            'type' => \App\Core\Enums\DocumentType::NationalId,
            'version' => 1,
        ]);
        $latest = ListingDocument::factory()->create([
            'listing_id' => $listing->id,
            'type' => \App\Core\Enums\DocumentType::NationalId,
            'version' => 2,
        ]);

        $this->get('/dashboard/documents/listing/'.$listing->id)
            ->assertOk()
            ->assertSee('v2', false)
            ->assertSee('v1', false)
            ->assertDontSee(__('aqdar.listings.doc_type.developer_status_letter'));

        $this->from('/dashboard/documents/listing/'.$listing->id)
            ->post('/dashboard/documents/'.$latest->id.'/reject', ['reason' => 'الصورة غير واضحة'])
            ->assertRedirect();
        $this->assertSame(DocumentReviewStatus::Rejected, $latest->fresh()->review_status);
    }

    public function test_unsigned_and_expired_downloads_are_forbidden(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create();
        Storage::disk('private_documents')->put('listings/9/id.pdf', '%PDF-1.4 test');
        $document = ListingDocument::factory()->create([
            'listing_id' => $listing->id,
            'path' => 'listings/9/id.pdf',
            'mime' => 'application/pdf',
        ]);

        $this->get('/dashboard/documents/'.$document->id.'/download')->assertForbidden();

        $url = URL::temporarySignedRoute('dashboard.documents.download', now()->addMinutes(10), ['document' => $document]);
        $this->travel(11)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_pdf_download_is_watermarked_with_reviewer_identity(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create(['public_code' => 'AQ-4409']);
        $pdf = (new PdfWatermarker)->minimal('National ID');
        Storage::disk('private_documents')->put('listings/'.$listing->id.'/id.pdf', $pdf);
        $document = ListingDocument::factory()->create([
            'listing_id' => $listing->id,
            'path' => 'listings/'.$listing->id.'/id.pdf',
            'mime' => 'application/pdf',
            'original_name' => 'national_id.pdf',
        ]);

        $url = URL::temporarySignedRoute('dashboard.documents.download', now()->addMinutes(10), ['document' => $document]);
        $response = $this->get($url)->assertOk();
        $body = $response->streamedContent();

        $this->assertStringContainsString('National ID', $body);
        $this->assertStringContainsString('AQDAR staff-'.$staff->id, $body);
    }
}
