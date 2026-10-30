<?php

namespace Tests\Integration;

use App\Core\Enums\CaseDocumentKind;
use App\Core\Enums\DocumentType;
use App\Core\Enums\MediaModerationStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Crm\Services\CaseService;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingMedia;
use App\Modules\Listing\Services\ListingFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class ListingPhase17Test extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_documents');
        Storage::fake('private_media');
        Storage::fake('public_media');
    }

    public function test_exe_payload_named_pdf_is_rejected_before_store(): void
    {
        $listing = Listing::factory()->create();
        $file = UploadedFile::fake()->createWithContent('id.pdf', "MZ\x90".str_repeat("\0", 80));

        try {
            app(ListingFileService::class)->storeDocument(
                $listing,
                $listing->owner,
                DocumentType::NationalId,
                $file,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertSame(0, $listing->documents()->count());
    }

    public function test_http_upload_rejects_exe_payload_named_pdf(): void
    {
        $listing = Listing::factory()->create();
        Sanctum::actingAs($listing->owner);

        $this->post("/api/v1/my/listings/{$listing->id}/documents", [
            'type' => 'national_id',
            'file' => UploadedFile::fake()->createWithContent('id.pdf', "MZ\x90".str_repeat("\0", 80)),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertSame(0, $listing->documents()->count());
    }

    public function test_case_document_exe_payload_is_rejected(): void
    {
        $case = TransferCase::factory()->create(['missing_documents_count' => 1]);
        $file = UploadedFile::fake()->createWithContent('letter.pdf', "MZ\x90".str_repeat("\0", 80));

        try {
            app(CaseService::class)->storeDocument(
                $case,
                $file,
                CaseDocumentKind::BuyerRequested,
                $case->buyer,
                null,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertSame(0, $case->documents()->count());
        $this->assertSame(1, (int) $case->fresh()->missing_documents_count);
    }

    public function test_http_case_upload_rejects_exe_payload_named_pdf(): void
    {
        $case = TransferCase::factory()->create(['missing_documents_count' => 1]);
        Sanctum::actingAs($case->buyer);

        $this->post('/api/v1/my/cases/'.$case->id.'/documents', [
            'kind' => 'buyer_requested',
            'file' => UploadedFile::fake()->createWithContent('letter.pdf', "MZ\x90".str_repeat("\0", 80)),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertSame(0, $case->documents()->count());
    }

    public function test_approved_video_stays_on_private_disk(): void
    {
        $listing = Listing::factory()->create();
        Sanctum::actingAs($listing->owner);

        $created = $this->post("/api/v1/my/listings/{$listing->id}/media", [
            'file' => UploadedFile::fake()->create('clip.mp4', 80, 'video/mp4'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.mime', 'video/mp4')
            ->assertJsonPath('data.kind', 'video')
            ->assertJsonPath('data.url', null);

        $media = ListingMedia::query()->findOrFail($created->json('data.id'));
        $this->assertTrue($media->isVideo());
        $this->assertNull($media->public_path);
        Storage::disk('private_media')->assertExists($media->original_path);

        $staff = $this->makeStaff(StaffRole::Reviewer);
        app(ListingFileService::class)->reviewMedia($media, $staff, MediaModerationStatus::Approved);

        $media->refresh();
        $this->assertSame(MediaModerationStatus::Approved, $media->moderation_status);
        $this->assertNull($media->public_path);
        $this->assertFalse($media->isApproved());
        Storage::disk('private_media')->assertExists($media->original_path);
        $this->assertSame([], Storage::disk('public_media')->allFiles());

        $payload = $this->getJson('/api/v1/my/listings/'.$listing->id)->assertOk();
        $rows = $payload->json('data.media.data') ?? $payload->json('data.media');
        $this->assertIsArray($rows);
        $this->assertArrayHasKey('url', $rows[0]);
        $this->assertNull($rows[0]['url']);
        $this->assertSame('approved', $rows[0]['moderation_status']);
        $this->assertSame('video', $rows[0]['kind']);
    }

    public function test_public_listing_omits_approved_video_without_public_path(): void
    {
        $listing = Listing::factory()->published()->create();
        ListingMedia::factory()->create([
            'listing_id' => $listing->id,
            'mime' => 'video/mp4',
            'original_path' => 'listings/'.$listing->id.'/clip.mp4',
            'moderation_status' => MediaModerationStatus::Approved,
            'public_path' => null,
        ]);
        ListingMedia::factory()->approved()->create([
            'listing_id' => $listing->id,
            'mime' => 'image/jpeg',
            'original_path' => 'listings/'.$listing->id.'/orig.jpg',
            'public_path' => 'listings/'.$listing->id.'/public.jpg',
        ]);
        Storage::disk('public_media')->put('listings/'.$listing->id.'/public.jpg', 'jpeg-bytes');

        $photos = $this->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->json('data.photos');
        $this->assertCount(1, $photos);
        $this->assertSame('photo', $photos[0]['kind']);
        $this->assertNotSame('video/mp4', $photos[0]['mime']);

        $media = $this->getJson('/api/v1/listings/'.$listing->id.'/media')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $media);
        $this->assertSame('photo', $media[0]['kind']);
        $this->assertSame([], array_values(array_filter($media, fn ($row) => ($row['kind'] ?? '') === 'video')));
    }
}
