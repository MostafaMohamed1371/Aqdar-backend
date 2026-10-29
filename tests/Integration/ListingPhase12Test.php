<?php

namespace Tests\Integration;

use App\Core\Enums\DocumentReviewStatus;
use App\Core\Enums\DocumentType;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use App\Modules\Listing\Models\ListingFieldSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\Concerns\UploadsListingDocuments;
use Tests\TestCase;

class ListingPhase12Test extends TestCase
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

    public function test_approve_and_publish_fail_when_required_documents_are_pending(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);
        $this->seedRequiredDocuments($listing);

        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/approve', ['reason' => 'اعتماد'])
            ->assertRedirect('/dashboard/listings/'.$listing->id)
            ->assertSessionHasErrors('documents');
        $this->assertSame(ListingStatus::InReview, $listing->fresh()->status);

        $listing->forceFill(['status' => ListingStatus::Approved])->save();
        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'نشر'])
            ->assertRedirect('/dashboard/listings/'.$listing->id)
            ->assertSessionHasErrors('documents');
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
    }

    public function test_unpublish_removes_listing_from_public_search(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $listing = Listing::factory()->published()->create();
        $this->getJson('/api/v1/listings/'.$listing->id)->assertOk();

        $this->post('/dashboard/listings/'.$listing->id.'/unpublish', ['reason' => 'سحب من السوق'])
            ->assertRedirect();
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
        $this->getJson('/api/v1/listings/'.$listing->id.'/media')->assertNotFound();
    }

    public function test_published_public_field_edit_moves_listing_back_to_review(): void
    {
        $listing = Listing::factory()->published()->create([
            'title_ar' => 'عنوان منشور',
        ]);
        Sanctum::actingAs($listing->owner);

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/specs', [
            'title_ar' => 'عنوان بعد التعديل',
        ])->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath('data.title_ar', 'عنوان بعد التعديل');

        $this->assertSame(ListingStatus::InReview, $listing->fresh()->status);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
    }

    public function test_resubmit_exposes_previous_field_snapshot(): void
    {
        $listing = Listing::factory()->needsChanges()->create([
            'title_ar' => 'الوصف القديم',
            'version' => 1,
        ]);
        ListingFieldSnapshot::query()->create([
            'listing_id' => $listing->id,
            'version' => 1,
            'payload' => ['title_ar' => 'الوصف القديم'],
        ]);
        Sanctum::actingAs($listing->owner);
        $this->seedRequiredDocuments($listing);

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/specs', [
            'title_ar' => 'الوصف الجديد بعد التعديل',
        ])->assertOk();

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/resubmit')
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath('data.title_ar', 'الوصف الجديد بعد التعديل')
            ->assertJsonPath('data.previous_snapshot.version', 1)
            ->assertJsonPath('data.previous_snapshot.payload.title_ar', 'الوصف القديم');
    }

    public function test_under_offer_stays_public_and_archived_does_not(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $listing = Listing::factory()->published()->create();

        $this->post('/dashboard/listings/'.$listing->id.'/under-offer', ['reason' => 'عرض قائم'])
            ->assertRedirect();
        $this->assertSame(ListingStatus::UnderOffer, $listing->fresh()->status);
        $this->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'under_offer');

        $this->post('/dashboard/listings/'.$listing->id.'/archive', ['reason' => 'سحب نهائي'])
            ->assertRedirect();
        $this->assertSame(ListingStatus::Archived, $listing->fresh()->status);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
    }

    public function test_latest_document_version_must_be_approved_even_if_older_version_is(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);
        $this->seedApprovedRequiredDocuments($listing);
        ListingDocument::factory()->create([
            'listing_id' => $listing->id,
            'type' => DocumentType::NationalId,
            'version' => 2,
            'review_status' => DocumentReviewStatus::Pending,
        ]);

        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/approve', ['reason' => 'اعتماد'])
            ->assertRedirect('/dashboard/listings/'.$listing->id)
            ->assertSessionHasErrors('documents');
        $this->assertSame(ListingStatus::InReview, $listing->fresh()->status);

        $listing->forceFill(['status' => ListingStatus::Approved])->save();
        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'نشر'])
            ->assertRedirect('/dashboard/listings/'.$listing->id)
            ->assertSessionHasErrors('documents');
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
    }

    public function test_submit_exposes_previous_snapshot_while_needs_changes(): void
    {
        $listing = Listing::factory()->create([
            'title_ar' => 'العنوان الأصلي',
            'status' => ListingStatus::Draft,
        ]);
        Sanctum::actingAs($listing->owner);
        $this->seedRequiredDocuments($listing);

        $this->postJson('/api/v1/my/listings/'.$listing->id.'/submit', ['consent' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath('data.previous_snapshot.version', 1)
            ->assertJsonPath('data.previous_snapshot.payload.title_ar', 'العنوان الأصلي');

        $this->actingAsStaff(StaffRole::Reviewer);
        $this->post('/dashboard/listings/'.$listing->id.'/request-changes', ['reason' => 'راجع العنوان'])
            ->assertRedirect();
        $this->assertSame(ListingStatus::NeedsChanges, $listing->fresh()->status);

        Sanctum::actingAs($listing->owner);
        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/specs', [
            'title_ar' => 'العنوان بعد التعديل',
        ])->assertOk()
            ->assertJsonPath('data.status', 'needs_changes')
            ->assertJsonPath('data.title_ar', 'العنوان بعد التعديل')
            ->assertJsonPath('data.previous_snapshot.version', 1)
            ->assertJsonPath('data.previous_snapshot.payload.title_ar', 'العنوان الأصلي')
            ->assertJsonPath('data.reviewer_notes.0', 'راجع العنوان');

        $this->getJson('/api/v1/my/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_changes')
            ->assertJsonPath('data.title_ar', 'العنوان بعد التعديل')
            ->assertJsonPath('data.previous_snapshot.version', 1)
            ->assertJsonPath('data.previous_snapshot.payload.title_ar', 'العنوان الأصلي')
            ->assertJsonPath('data.reviewer_notes.0', 'راجع العنوان');
    }

    public function test_published_financials_and_media_edits_move_listing_back_to_review(): void
    {
        $listing = Listing::factory()->published()->create();
        Sanctum::actingAs($listing->owner);

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/financials', [
            'declared_asking_price' => 2100000,
        ])->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath('data.financials.declared_asking_price', 2100000);

        $this->assertSame(ListingStatus::InReview, $listing->fresh()->status);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();

        $live = Listing::factory()->published()->create();
        Sanctum::actingAs($live->owner);
        $this->post('/api/v1/my/listings/'.$live->id.'/media', [
            'file' => UploadedFile::fake()->image('unit.jpg', 400, 300),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(ListingStatus::InReview, $live->fresh()->status);
        $this->getJson('/api/v1/listings/'.$live->id)->assertNotFound();
    }

    public function test_unpublish_hides_share_page_and_under_offer_keeps_it(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $listing = Listing::factory()->published()->create();
        $this->get('/property/'.$listing->public_code)->assertOk();

        $this->post('/dashboard/listings/'.$listing->id.'/unpublish', ['reason' => 'سحب من السوق'])
            ->assertRedirect();
        $this->get('/property/'.$listing->public_code)->assertNotFound();

        $offer = Listing::factory()->published()->create();
        $this->post('/dashboard/listings/'.$offer->id.'/under-offer', ['reason' => 'عرض قائم'])
            ->assertRedirect();
        $this->get('/property/'.$offer->public_code)->assertOk();
        $this->getJson('/api/v1/listings/'.$offer->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'under_offer');
    }

    public function test_my_listings_published_chip_includes_under_offer(): void
    {
        $user = Listing::factory()->published()->create()->owner;
        $published = Listing::query()->where('user_id', $user->id)->first();
        $offer = Listing::factory()->published()->create([
            'user_id' => $user->id,
            'status' => ListingStatus::UnderOffer,
        ]);
        $draft = Listing::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        foreach (['published', 'live'] as $chip) {
            $this->getJson('/api/v1/my/listings?status='.$chip)
                ->assertOk()
                ->assertJsonFragment(['id' => $published->id])
                ->assertJsonFragment(['id' => $offer->id])
                ->assertJsonMissing(['id' => $draft->id]);
        }

        $this->getJson('/api/v1/my/listings?status=under_offer')
            ->assertOk()
            ->assertJsonFragment(['id' => $offer->id])
            ->assertJsonMissing(['id' => $published->id]);
    }

    public function test_desk_index_matches_adm02_tabs_and_can_archive_from_the_list(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $listing = Listing::factory()->published()->create();

        $this->withSession(['locale' => 'ar'])
            ->get('/dashboard/listings')
            ->assertOk()
            ->assertSee(__('aqdar.listings.desk.tab_hold', locale: 'ar'), false)
            ->assertSee(__('aqdar.listings.desk.tab_needs_changes', locale: 'ar'), false)
            ->assertSee(__('aqdar.listings.desk.hold', locale: 'ar'), false)
            ->assertSee(__('aqdar.listings.desk.archive', locale: 'ar'), false)
            ->assertSee(route('dashboard.listings.hold', $listing), false)
            ->assertSee(route('dashboard.listings.archive', $listing), false);

        $this->from('/dashboard/listings')
            ->post('/dashboard/listings/'.$listing->id.'/archive', [
                'reason' => __('aqdar.listings.desk.archive_default'),
            ])
            ->assertRedirect();
        $this->assertSame(ListingStatus::Archived, $listing->fresh()->status);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
    }
}
