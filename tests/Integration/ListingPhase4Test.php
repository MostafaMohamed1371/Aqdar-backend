<?php

namespace Tests\Integration;

use App\Core\Enums\DistressedChild;
use App\Core\Enums\DocumentReviewStatus;
use App\Core\Enums\DocumentType;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Core\Support\PublicMediaUrl;
use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Category\Models\Category;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use App\Modules\Listing\Models\ListingMedia;
use App\Modules\Listing\Services\ListingFileService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\Concerns\UploadsListingDocuments;
use Tests\TestCase;

class ListingPhase4Test extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;
    use UploadsListingDocuments;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_documents');
        Storage::fake('private_media');
        Storage::fake('public_media');
    }

    public function test_submit_requires_mandatory_documents(): void
    {
        $this->marketplaceUser();
        $refs = $this->seedCatalog();
        $id = $this->postJson('/api/v1/my/listings')->json('data.id');
        $this->putJson("/api/v1/my/listings/{$id}/step/category", ['category_id' => $refs['non']->id]);
        $this->putJson("/api/v1/my/listings/{$id}/step/specs", [
            'governorate_id' => $refs['area']->governorate_id,
            'area_id' => $refs['area']->id,
            'unit_type_id' => $refs['unit']->id,
            'title_ar' => 'شقة إعادة بيع',
            'description_ar' => 'وصف كامل للوحدة بدون أي رقم هاتف.',
            'area_sqm' => 110,
            'bedrooms' => 2,
        ]);
        $this->putJson("/api/v1/my/listings/{$id}/step/financials", [
            'declared_contract_price' => 1000000,
            'declared_asking_price' => 950000,
        ]);

        $response = $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson("/api/v1/my/listings/{$id}/submit", ['consent' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documents')
            ->assertJsonPath('code', 'validation')
            ->assertJsonPath('title', __('aqdar.flash.validation_title', locale: 'ar'));

        $message = (string) $response->json('errors.documents.0');
        $this->assertStringContainsString('بطاقة الرقم القومي', $message);
        $this->assertStringContainsString('عقد المطور', $message);
        $this->assertStringContainsString('إيصالات السداد', $message);
        $this->assertStringNotContainsString('national_id', $message);
        $this->assertStringNotContainsString('payment_proof', $message);
        $this->assertStringNotContainsString('documents.', $message);
        $encoded = json_encode($response->json());
        $this->assertStringNotContainsString('national_id', (string) $encoded);
        $this->assertStringNotContainsString('payment_proof', (string) $encoded);

        $this->attachRequiredDocuments((int) $id);
        $this->postJson("/api/v1/my/listings/{$id}/submit", ['consent' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');
    }

    public function test_uat04_private_document_has_no_public_url(): void
    {
        $this->marketplaceUser();
        $listing = Listing::factory()->create();
        Sanctum::actingAs($listing->owner);
        $this->attachRequiredDocuments($listing->id);
        $document = ListingDocument::query()->firstOrFail();

        $this->getJson('/api/v1/listings/'.$listing->id.'/documents')->assertNotFound();
        $this->assertContains(
            $this->get('/storage/documents/'.$document->path)->status(),
            [403, 404],
        );
        $this->getJson('/api/v1/listings/'.$listing->id)
            ->assertNotFound();

        $encoded = json_encode($this->getJson('/api/v1/my/listings/'.$listing->id)->json());
        $this->assertStringNotContainsString($document->path, (string) $encoded);
        $this->assertStringNotContainsString('private/documents', (string) $encoded);
    }

    public function test_uat07_media_with_contact_details_stays_held(): void
    {
        $listing = Listing::factory()->create();
        Sanctum::actingAs($listing->owner);

        $this->post("/api/v1/my/listings/{$listing->id}/media", [
            'file' => UploadedFile::fake()->image('unit.jpg', 400, 300),
            'caption' => 'للتواصل 01012345678',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.moderation_status', 'held')
            ->assertJsonPath('data.hold_reason', 'phone')
            ->assertJsonPath('data.url', null);

        $listing->forceFill(['status' => ListingStatus::Published, 'published_at' => now()])->save();
        $this->getJson('/api/v1/listings/'.$listing->id.'/media')
            ->assertOk()
            ->assertExactJson(['data' => []]);
        $this->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.photos', []);
    }

    public function test_approved_media_appears_on_public_listing_only(): void
    {
        $listing = Listing::factory()->published()->create();
        $media = ListingMedia::factory()->approved()->create([
            'listing_id' => $listing->id,
            'public_path' => 'listings/'.$listing->id.'/1.jpg',
        ]);
        Storage::disk('public_media')->put($media->public_path, 'jpeg-bytes');

        $this->getJson('/api/v1/listings/'.$listing->id.'/media')
            ->assertOk()
            ->assertJsonPath('data.0.id', $media->id)
            ->assertJsonPath('data.0.url', PublicMediaUrl::for($media->public_path));

        $public = $this->getJson('/api/v1/listings/'.$listing->id)->assertOk();
        $this->assertNotEmpty($public->json('data.photos'));
        $this->assertArrayNotHasKey('documents', $public->json('data'));
        $encoded = json_encode($public->json());
        $this->assertStringNotContainsString('original_path', $encoded);
    }

    public function test_public_photo_url_follows_the_request_host(): void
    {
        $listing = Listing::factory()->published()->create();
        ListingMedia::factory()->approved()->create([
            'listing_id' => $listing->id,
            'public_path' => 'listings/'.$listing->id.'/1.jpg',
        ]);

        $this->getJson('http://10.0.2.2:8000/api/v1/listings/'.$listing->id.'/media')
            ->assertOk()
            ->assertJsonPath('data.0.url', 'http://10.0.2.2:8000/storage/media/listings/'.$listing->id.'/1.jpg');
    }

    public function test_owner_document_list_does_not_include_file_path(): void
    {
        $listing = Listing::factory()->create();
        Sanctum::actingAs($listing->owner);
        $this->attachRequiredDocuments($listing->id);

        $response = $this->getJson('/api/v1/my/documents')->assertOk();
        $types = collect($response->json('data'))->pluck('type')->all();
        $this->assertEqualsCanonicalizing(['national_id', 'contract', 'payment_proof'], $types);
        $this->assertArrayNotHasKey('path', $response->json('data.0'));
        $this->assertArrayNotHasKey('disk', $response->json('data.0'));
    }

    public function test_distressed_submit_requires_developer_status_letter(): void
    {
        $this->marketplaceUser();
        $refs = $this->seedCatalog();
        $id = $this->postJson('/api/v1/my/listings')->json('data.id');
        $this->putJson("/api/v1/my/listings/{$id}/step/category", [
            'category_id' => $refs['distressed']->id,
            'distressed_child' => DistressedChild::CurrentDefault->value,
        ])->assertOk()->assertJsonPath('data.segment', 'distressed');
        $this->putJson("/api/v1/my/listings/{$id}/step/specs", [
            'governorate_id' => $refs['area']->governorate_id,
            'area_id' => $refs['area']->id,
            'unit_type_id' => $refs['unit']->id,
            'title_ar' => 'وحدة متعثرة',
            'description_ar' => 'وصف كامل بدون بيانات تواصل للوحدة المتعثرة.',
            'area_sqm' => 90,
            'bedrooms' => 2,
        ]);
        $this->putJson("/api/v1/my/listings/{$id}/step/financials", [
            'declared_contract_price' => 800000,
            'declared_asking_price' => 700000,
        ]);
        $this->attachRequiredDocuments((int) $id);
        $this->postJson("/api/v1/my/listings/{$id}/submit", ['consent' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documents');

        $this->post("/api/v1/my/listings/{$id}/documents", [
            'type' => 'developer_status_letter',
            'file' => UploadedFile::fake()->create('letter.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->postJson("/api/v1/my/listings/{$id}/submit", ['consent' => true])
            ->assertOk()
            ->assertJsonPath('data.segment', 'distressed');
    }

    public function test_owner_cannot_upload_to_another_sellers_listing(): void
    {
        $mine = Listing::factory()->create();
        $other = Listing::factory()->create();
        Sanctum::actingAs($mine->owner);

        $this->post("/api/v1/my/listings/{$other->id}/documents", [
            'type' => 'national_id',
            'file' => UploadedFile::fake()->create('id.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_invalid_document_mime_is_rejected(): void
    {
        $listing = Listing::factory()->create();
        Sanctum::actingAs($listing->owner);

        $this->post("/api/v1/my/listings/{$listing->id}/documents", [
            'type' => 'national_id',
            'file' => UploadedFile::fake()->create('virus.exe', 20, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_media_filename_contact_leak_is_held_and_can_be_deleted(): void
    {
        $listing = Listing::factory()->create();
        Sanctum::actingAs($listing->owner);

        $created = $this->post("/api/v1/my/listings/{$listing->id}/media", [
            'file' => UploadedFile::fake()->image('whatsapp-01012345678.jpg', 400, 300),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.moderation_status', 'held');

        $mediaId = $created->json('data.id');
        $this->delete("/api/v1/my/listings/{$listing->id}/media/{$mediaId}", [], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertDatabaseMissing('listing_media', ['id' => $mediaId]);
    }

    public function test_video_upload_skips_image_processing(): void
    {
        $listing = Listing::factory()->create();
        Sanctum::actingAs($listing->owner);

        $this->post("/api/v1/my/listings/{$listing->id}/media", [
            'file' => UploadedFile::fake()->create('clip.mp4', 80, 'video/mp4'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.mime', 'video/mp4')
            ->assertJsonPath('data.kind', 'video')
            ->assertJsonPath('data.url', null);
    }

    public function test_owner_can_replace_document_while_listing_is_in_review(): void
    {
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);
        $document = ListingDocument::factory()->create([
            'listing_id' => $listing->id,
            'type' => DocumentType::NationalId,
            'review_status' => DocumentReviewStatus::Pending,
        ]);
        $staff = $this->makeStaff(StaffRole::Reviewer);
        app(ListingFileService::class)->reviewDocument(
            $document,
            $staff,
            DocumentReviewStatus::ReplacementRequested,
            'الصورة غير واضحة',
        );

        Sanctum::actingAs($listing->owner);
        $this->post("/api/v1/my/listings/{$listing->id}/documents", [
            'type' => 'national_id',
            'file' => UploadedFile::fake()->create('national_id-v2.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.review_status', 'pending')
            ->assertJsonMissingPath('data.path');

        $this->post("/api/v1/my/listings/{$listing->id}/documents", [
            'type' => 'contract',
            'file' => UploadedFile::fake()->create('contract.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    /** @return array{non: Category, distressed: Category, area: Area, unit: UnitType} */
    private function seedCatalog(): array
    {
        $this->seed(CatalogSeeder::class);

        return [
            'non' => Category::query()->where('slug', 'non-distressed')->firstOrFail(),
            'distressed' => Category::query()->where('slug', 'distressed')->firstOrFail(),
            'area' => Area::query()->where('slug', 'nasr-city')->firstOrFail(),
            'unit' => UnitType::query()->where('slug', 'apartment')->firstOrFail(),
        ];
    }

    private function marketplaceUser(): User
    {
        $user = User::factory()->create(['is_seller' => false, 'is_buyer' => true]);
        Sanctum::actingAs($user);

        return $user;
    }
}
