<?php

namespace Tests\Integration;

use App\Core\Enums\DistressedChild;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\MarketSegment;
use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Models\Amenity;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Category\Models\Category;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\UploadsListingDocuments;
use Tests\TestCase;

class ListingPhase3Test extends TestCase
{
    use RefreshDatabase;
    use UploadsListingDocuments;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_documents');
        Storage::fake('private_media');
        Storage::fake('public_media');
    }

    public function test_seller_can_start_complete_steps_and_submit_for_review(): void
    {
        $user = $this->marketplaceUser();
        $refs = $this->seedCatalog();

        $created = $this->postJson('/api/v1/my/listings')->assertCreated();
        $id = $created->json('data.id');
        $this->assertNotNull($created->json('data.public_code'));
        $this->assertTrue($user->fresh()->is_seller);

        $this->putJson("/api/v1/my/listings/{$id}/step/category", [
            'category_id' => $refs['non']->id,
        ])->assertOk()->assertJsonPath('data.segment', 'non_distressed');

        $this->putJson("/api/v1/my/listings/{$id}/step/specs", [
            'governorate_id' => $refs['area']->governorate_id,
            'area_id' => $refs['area']->id,
            'unit_type_id' => $refs['unit']->id,
            'title_ar' => 'شقة إعادة بيع',
            'description_ar' => 'وصف كامل للوحدة بدون أي رقم هاتف.',
            'area_sqm' => 110,
            'bedrooms' => 2,
            'bathrooms' => 1,
        ])->assertOk();

        $this->putJson("/api/v1/my/listings/{$id}/step/financials", [
            'declared_contract_price' => 1000000,
            'declared_total_paid' => 400000,
            'declared_remaining' => 700000,
            'declared_asking_price' => 950000,
        ])->assertOk()
            ->assertJsonPath('data.financials.declared_total_paid', 400000)
            ->assertJsonPath('data.financials.declared_remaining', 700000);

        $this->attachRequiredDocuments((int) $id);

        $submit = $this->postJson("/api/v1/my/listings/{$id}/submit", ['consent' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath('data.contact_flagged', false);
        $this->assertSame($created->json('data.public_code').'-R1', $submit->json('data.review_reference'));

        $this->getJson("/api/v1/listings/{$id}")->assertNotFound();
        $this->getJson("/api/v1/my/listings/{$id}/history")
            ->assertOk()
            ->assertJsonFragment(['to_status' => 'submitted'])
            ->assertJsonFragment(['to_status' => 'in_review']);
    }

    public function test_uat05_guest_cannot_open_unpublished_listing(): void
    {
        $listing = Listing::factory()->create(['status' => ListingStatus::Draft]);

        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
        $this->getJson('/api/v1/listings/'.$listing->id.'/media')->assertNotFound();
    }

    public function test_uat08_public_listing_json_has_no_seller_pii_or_documents(): void
    {
        $owner = User::factory()->seller()->create([
            'phone' => '+201055556666',
            'email' => 'owner-secret@example.com',
        ]);
        $listing = Listing::factory()->published()->create(['user_id' => $owner->id]);

        $response = $this->getJson('/api/v1/listings/'.$listing->id)->assertOk();
        $json = $response->json();

        $response->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.phone')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.documents_meta')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonStructure(['data' => ['sales_contact' => ['name', 'phone', 'hours']]]);

        $encoded = json_encode($json);
        $this->assertStringNotContainsString('+201055556666', $encoded);
        $this->assertStringNotContainsString('owner-secret@example.com', $encoded);
        $this->assertStringNotContainsString('"user_id"', $encoded);
        $this->assertStringNotContainsString('document', strtolower($encoded));
    }

    public function test_uat06_description_with_phone_is_held_with_a_logged_reason(): void
    {
        $this->marketplaceUser();
        $refs = $this->seedCatalog();
        $id = $this->postJson('/api/v1/my/listings')->json('data.id');

        $this->putJson("/api/v1/my/listings/{$id}/step/category", [
            'category_id' => $refs['non']->id,
        ]);
        $this->putJson("/api/v1/my/listings/{$id}/step/specs", [
            'governorate_id' => $refs['area']->governorate_id,
            'area_id' => $refs['area']->id,
            'unit_type_id' => $refs['unit']->id,
            'title_ar' => 'شقة للبيع',
            'description_ar' => 'للتواصل 01012345678 بعد السادسة.',
            'area_sqm' => 90,
            'bedrooms' => 2,
        ]);
        $this->putJson("/api/v1/my/listings/{$id}/step/financials", [
            'declared_contract_price' => 800000,
            'declared_asking_price' => 750000,
        ]);
        $this->attachRequiredDocuments((int) $id);

        $this->postJson("/api/v1/my/listings/{$id}/submit", ['consent' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath('data.contact_flagged', true)
            ->assertJsonPath('data.contact_flag_reason', 'phone');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'listing.held_contact',
            'subject_id' => $id,
        ]);
        $this->getJson("/api/v1/listings/{$id}")->assertNotFound();
    }

    public function test_another_user_cannot_open_someone_elses_draft(): void
    {
        $listing = Listing::factory()->create();
        $this->marketplaceUser();

        $this->getJson('/api/v1/my/listings/'.$listing->id)->assertNotFound();
        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/specs', [
            'title_ar' => 'قرصنة',
        ])->assertNotFound();
    }

    public function test_steps_cannot_be_edited_after_submit(): void
    {
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);
        Sanctum::actingAs($listing->owner);

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/specs', [
            'title_ar' => 'تعديل بعد الإرسال',
        ])->assertForbidden();
    }

    public function test_resubmit_from_needs_changes(): void
    {
        $listing = Listing::factory()->needsChanges()->create();
        Sanctum::actingAs($listing->owner);
        $this->seedRequiredDocuments($listing);

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/specs', [
            'description_ar' => 'تم تصحيح الوصف بالكامل بدون تواصل.',
        ])->assertOk();

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/resubmit')
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');
    }

    public function test_distressed_requires_child_a_or_b(): void
    {
        $this->marketplaceUser();
        $refs = $this->seedCatalog();
        $id = $this->postJson('/api/v1/my/listings')->json('data.id');

        $this->putJson("/api/v1/my/listings/{$id}/step/category", [
            'category_id' => $refs['distressed']->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('distressed_child');
    }

    public function test_retired_unit_type_is_rejected(): void
    {
        $this->marketplaceUser();
        $refs = $this->seedCatalog();
        UnitType::query()->where('id', $refs['unit']->id)->update(['is_active' => false]);
        $id = $this->postJson('/api/v1/my/listings')->json('data.id');

        $this->putJson("/api/v1/my/listings/{$id}/step/category", [
            'category_id' => $refs['non']->id,
        ]);
        $this->putJson("/api/v1/my/listings/{$id}/step/specs", [
            'unit_type_id' => $refs['unit']->id,
            'governorate_id' => $refs['area']->governorate_id,
            'area_id' => $refs['area']->id,
            'title_ar' => 'شقة',
            'description_ar' => 'وصف طويل بما يكفي للتحقق.',
            'area_sqm' => 80,
            'bedrooms' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('unit_type_id');
    }

    public function test_public_search_filters_and_hides_unpublished(): void
    {
        $cairo = Listing::factory()->published()->create(['title_ar' => 'منشور القاهرة', 'area_sqm' => 200]);
        Listing::factory()->create(['status' => ListingStatus::Draft, 'title_ar' => 'مسودة']);
        Listing::factory()->published()->create([
            'governorate_id' => $cairo->governorate_id,
            'area_id' => $cairo->area_id,
            'title_ar' => 'رخيص',
            'area_sqm' => 70,
        ]);

        $cheap = Listing::query()->where('title_ar', 'رخيص')->firstOrFail();
        $cheap->financials()->update(['declared_asking_price' => 100000]);
        $cairo->financials()->update(['declared_asking_price' => 3000000]);

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonMissing(['title' => 'مسودة']);

        $this->getJson('/api/v1/listings?price_max=200000')
            ->assertOk()
            ->assertJsonFragment(['id' => $cheap->id])
            ->assertJsonMissing(['id' => $cairo->id]);

        $this->getJson('/api/v1/listings?area_min=150')
            ->assertOk()
            ->assertJsonFragment(['id' => $cairo->id])
            ->assertJsonMissing(['id' => $cheap->id]);
    }

    public function test_map_returns_rounded_pins_for_published_listings(): void
    {
        $listing = Listing::factory()->published()->create([
            'lat' => 30.0444201,
            'lng' => 31.2357119,
        ]);

        $this->getJson('/api/v1/map')
            ->assertOk()
            ->assertJsonFragment(['id' => $listing->id])
            ->assertJsonPath('data.0.lat', 30.044)
            ->assertJsonPath('data.0.lng', 31.236);
    }

    public function test_favorites_only_work_for_published_listings(): void
    {
        $user = $this->marketplaceUser();
        $published = Listing::factory()->published()->create();
        $draft = Listing::factory()->create();

        $this->postJson('/api/v1/favorites/'.$draft->id)->assertUnprocessable();
        $this->postJson('/api/v1/favorites/'.$published->id)->assertCreated();
        $this->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonFragment(['id' => $published->id]);
        $this->deleteJson('/api/v1/favorites/'.$published->id)->assertOk();
        $this->getJson('/api/v1/favorites')->assertJsonMissing(['id' => $published->id]);
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'listing_id' => $published->id,
        ]);
    }

    public function test_published_media_endpoint_is_empty_without_approved_photos(): void
    {
        $listing = Listing::factory()->published()->create();

        $this->getJson('/api/v1/listings/'.$listing->id.'/media')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_guest_cannot_create_or_favorite(): void
    {
        $listing = Listing::factory()->published()->create();

        $this->postJson('/api/v1/my/listings')->assertUnauthorized();
        $this->postJson('/api/v1/favorites/'.$listing->id)->assertUnauthorized();
    }

    public function test_consent_is_required_to_submit(): void
    {
        $listing = Listing::factory()->create();
        Sanctum::actingAs($listing->owner);

        $this->postJson('/api/v1/my/listings/'.$listing->id.'/submit', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('consent');
        $this->postJson('/api/v1/my/listings/'.$listing->id.'/submit', ['consent' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('consent');
        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);
    }

    public function test_submit_from_in_review_is_rejected(): void
    {
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);
        Sanctum::actingAs($listing->owner);

        $this->postJson('/api/v1/my/listings/'.$listing->id.'/submit', ['consent' => true])
            ->assertForbidden();
    }

    public function test_distressed_child_category_sets_a_or_b(): void
    {
        $this->marketplaceUser();
        $this->seedCatalog();
        $childA = Category::query()->where('slug', 'distressed-a')->firstOrFail();
        $id = $this->postJson('/api/v1/my/listings')->json('data.id');

        $this->putJson("/api/v1/my/listings/{$id}/step/category", [
            'category_id' => $childA->id,
        ])->assertOk()
            ->assertJsonPath('data.segment', 'distressed')
            ->assertJsonPath('data.distressed_child', 'a');
    }

    public function test_media_and_documents_steps_are_placeholders(): void
    {
        $this->marketplaceUser();
        $id = $this->postJson('/api/v1/my/listings')->json('data.id');

        $this->putJson("/api/v1/my/listings/{$id}/step/media", [
            'media_meta' => ['pending' => true],
        ])->assertOk()->assertJsonPath('data.media_meta.pending', true);

        $this->putJson("/api/v1/my/listings/{$id}/step/documents", [
            'documents_meta' => ['pending' => true],
        ])->assertOk()->assertJsonPath('data.documents_meta.pending', true);
    }

    public function test_my_listings_only_returns_the_owner(): void
    {
        $user = $this->marketplaceUser();
        $mine = Listing::factory()->create(['user_id' => $user->id, 'title_ar' => 'وحدتي']);
        $other = Listing::factory()->create(['title_ar' => 'وحدة شخص آخر']);

        $this->getJson('/api/v1/my/listings')
            ->assertOk()
            ->assertJsonFragment(['id' => $mine->id])
            ->assertJsonMissing(['id' => $other->id]);
    }

    public function test_rejected_listing_is_hidden_from_public_search(): void
    {
        $rejected = Listing::factory()->create([
            'status' => ListingStatus::Rejected,
            'title_ar' => 'مرفوض',
        ]);

        $this->getJson('/api/v1/listings/'.$rejected->id)->assertNotFound();
        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonMissing(['id' => $rejected->id]);
    }

    public function test_down_payment_and_installment_filters(): void
    {
        $low = Listing::factory()->published()->create(['title_ar' => 'قسط منخفض']);
        $high = Listing::factory()->published()->create(['title_ar' => 'قسط مرتفع']);
        $low->financials()->update([
            'declared_remaining' => 50000,
            'declared_installment_amount' => 2000,
        ]);
        $high->financials()->update([
            'declared_remaining' => 900000,
            'declared_installment_amount' => 25000,
        ]);

        $this->getJson('/api/v1/listings?down_payment_max=100000')
            ->assertOk()
            ->assertJsonFragment(['id' => $low->id])
            ->assertJsonMissing(['id' => $high->id]);

        $this->getJson('/api/v1/listings?installment_max=5000')
            ->assertOk()
            ->assertJsonFragment(['id' => $low->id])
            ->assertJsonMissing(['id' => $high->id]);

        $this->getJson('/api/v1/listings?down_payment_min=100000')
            ->assertOk()
            ->assertJsonFragment(['id' => $high->id])
            ->assertJsonMissing(['id' => $low->id]);

        $this->getJson('/api/v1/listings?installment_min=10000')
            ->assertOk()
            ->assertJsonFragment(['id' => $high->id])
            ->assertJsonMissing(['id' => $low->id]);
    }

    public function test_non_integer_bedrooms_are_rejected(): void
    {
        $this->marketplaceUser();
        $id = $this->postJson('/api/v1/my/listings')->json('data.id');

        $this->putJson("/api/v1/my/listings/{$id}/step/specs", [
            'bedrooms' => 1.5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('bedrooms');
    }

    public function test_public_detail_includes_labelled_financials_amenities_and_tags(): void
    {
        $amenity = Amenity::factory()->create(['slug' => 'elevator', 'name_ar' => 'مصعد', 'name_en' => 'Elevator']);
        $tag = Tag::factory()->create(['slug' => 'featured', 'name_ar' => 'مميزة', 'name_en' => 'Featured']);
        $listing = Listing::factory()->published()->create([
            'amenity_ids' => [$amenity->id],
            'tag_ids' => [$tag->id],
        ]);
        $listing->financials()->update([
            'declared_contract_price' => 2900000,
            'declared_total_paid' => 1450000,
            'declared_remaining' => 1450000,
            'declared_overdue_count' => 2,
            'declared_overdue_amount' => 96000,
            'declared_installment_amount' => 48000,
            'declared_remaining_installments' => 30,
        ]);

        $this->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.financial_summary.contract_price.value', 2900000)
            ->assertJsonPath('data.financial_summary.contract_price.source', 'declared')
            ->assertJsonPath('data.financial_summary.total_paid.value', 1450000)
            ->assertJsonPath('data.financial_summary.remaining.value', 1450000)
            ->assertJsonPath('data.financial_summary.overdue_count.value', 2)
            ->assertJsonPath('data.amenities.0.slug', 'elevator')
            ->assertJsonPath('data.tags.0.slug', 'featured')
            ->assertJsonPath('data.is_favorited', false);
    }

    public function test_optional_auth_marks_public_listing_as_favorited(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->published()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/favorites/'.$listing->id)->assertCreated();
        $this->withToken($token)->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.is_favorited', true);
    }

    public function test_search_q_tag_sort_segment_and_pagination(): void
    {
        $tag = Tag::factory()->create(['slug' => 'featured']);
        $cheap = Listing::factory()->published()->create([
            'title_ar' => 'شقة مميزة رخيصة',
            'title_en' => 'Cheap featured apartment',
            'segment' => MarketSegment::NonDistressed,
            'tag_ids' => [$tag->id],
            'area_sqm' => 80,
        ]);
        $expensive = Listing::factory()->published()->create([
            'title_ar' => 'فيلا عادية',
            'title_en' => 'Ordinary villa',
            'segment' => MarketSegment::Distressed,
            'distressed_child' => DistressedChild::CurrentDefault,
            'tag_ids' => [],
            'area_sqm' => 300,
        ]);
        $cheap->financials()->update(['declared_asking_price' => 100000]);
        $expensive->financials()->update(['declared_asking_price' => 9000000]);

        $this->getJson('/api/v1/listings?q='.rawurlencode('مميزة'))
            ->assertOk()
            ->assertJsonFragment(['id' => $cheap->id])
            ->assertJsonMissing(['id' => $expensive->id]);

        $this->getJson('/api/v1/listings?tag=featured')
            ->assertOk()
            ->assertJsonFragment(['id' => $cheap->id])
            ->assertJsonMissing(['id' => $expensive->id]);

        $sorted = $this->getJson('/api/v1/listings?sort=price_asc')->assertOk();
        $this->assertSame($cheap->id, $sorted->json('data.0.id'));

        $this->getJson('/api/v1/listings?segment=distressed&distressed_child=a')
            ->assertOk()
            ->assertJsonFragment(['id' => $expensive->id])
            ->assertJsonMissing(['id' => $cheap->id]);

        $page = $this->getJson('/api/v1/listings?per_page=1')->assertOk();
        $this->assertCount(1, $page->json('data'));
        $this->assertGreaterThanOrEqual(2, $page->json('meta.total'));
    }

    public function test_listing_counts_and_locale_title(): void
    {
        Listing::factory()->published()->create([
            'segment' => MarketSegment::NonDistressed,
            'title_ar' => 'عنوان عربي',
            'title_en' => 'English title',
        ]);
        Listing::factory()->published()->create([
            'segment' => MarketSegment::Distressed,
            'distressed_child' => DistressedChild::ExpectedDefault,
        ]);

        $this->getJson('/api/v1/listings/counts')
            ->assertOk()
            ->assertJsonPath('data.non_distressed', 1)
            ->assertJsonPath('data.distressed', 1)
            ->assertJsonPath('data.distressed_a', 0)
            ->assertJsonPath('data.distressed_b', 1);

        $listing = Listing::query()->where('title_en', 'English title')->firstOrFail();
        $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'English title');
    }

    public function test_hold_and_closed_listings_are_not_public(): void
    {
        $hold = Listing::factory()->create(['status' => ListingStatus::Hold, 'title_ar' => 'موقوف']);
        $closed = Listing::factory()->create(['status' => ListingStatus::Closed, 'title_ar' => 'مغلق']);

        $this->getJson('/api/v1/listings/'.$hold->id)->assertNotFound();
        $this->getJson('/api/v1/listings/'.$closed->id)->assertNotFound();
        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonMissing(['id' => $hold->id])
            ->assertJsonMissing(['id' => $closed->id]);
    }

    public function test_my_listings_can_filter_by_status_chip(): void
    {
        $user = $this->marketplaceUser();
        $draft = Listing::factory()->create(['user_id' => $user->id, 'status' => ListingStatus::Draft]);
        $review = Listing::factory()->create(['user_id' => $user->id, 'status' => ListingStatus::InReview]);
        $published = Listing::factory()->published()->create(['user_id' => $user->id]);
        $offer = Listing::factory()->published()->create([
            'user_id' => $user->id,
            'status' => ListingStatus::UnderOffer,
        ]);

        $this->getJson('/api/v1/my/listings?status=draft')
            ->assertOk()
            ->assertJsonFragment(['id' => $draft->id])
            ->assertJsonMissing(['id' => $review->id]);

        $this->getJson('/api/v1/my/listings?status=review')
            ->assertOk()
            ->assertJsonFragment(['id' => $review->id])
            ->assertJsonMissing(['id' => $draft->id])
            ->assertJsonMissing(['id' => $published->id]);

        $this->getJson('/api/v1/my/listings?status=published')
            ->assertOk()
            ->assertJsonFragment(['id' => $published->id])
            ->assertJsonFragment(['id' => $offer->id])
            ->assertJsonMissing(['id' => $draft->id]);
    }

    public function test_installment_items_are_saved_and_not_auto_balanced(): void
    {
        $this->marketplaceUser();
        $id = $this->postJson('/api/v1/my/listings')->json('data.id');

        $this->putJson("/api/v1/my/listings/{$id}/step/financials", [
            'declared_contract_price' => 1000000,
            'declared_total_paid' => 200000,
            'declared_remaining' => 900000,
            'declared_asking_price' => 950000,
            'installment_items' => [
                ['sequence' => 1, 'amount' => 15000, 'due_date' => '2026-11-01'],
                ['sequence' => 2, 'amount' => 15000, 'note' => 'قسط ثاني'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.financials.declared_total_paid', 200000)
            ->assertJsonPath('data.financials.declared_remaining', 900000)
            ->assertJsonPath('data.installment_items.0.amount', 15000)
            ->assertJsonCount(2, 'data.installment_items');
    }

    public function test_email_whatsapp_and_qr_descriptions_are_flagged(): void
    {
        $this->marketplaceUser();
        $refs = $this->seedCatalog();

        foreach ([
            ['email me at leak@example.com', 'email'],
            ['تواصل واتساب بعد المغرب', 'url'],
            ['باركود على باب الوحدة', 'qr'],
        ] as [$description, $reason]) {
            $id = $this->postJson('/api/v1/my/listings')->json('data.id');
            $this->putJson("/api/v1/my/listings/{$id}/step/category", ['category_id' => $refs['non']->id]);
            $this->putJson("/api/v1/my/listings/{$id}/step/specs", [
                'governorate_id' => $refs['area']->governorate_id,
                'area_id' => $refs['area']->id,
                'unit_type_id' => $refs['unit']->id,
                'title_ar' => 'شقة للبيع',
                'description_ar' => $description.' مع وصف إضافي كاف للتحقق.',
                'area_sqm' => 90,
                'bedrooms' => 2,
            ]);
            $this->putJson("/api/v1/my/listings/{$id}/step/financials", [
                'declared_contract_price' => 800000,
                'declared_asking_price' => 750000,
            ]);
            $this->attachRequiredDocuments((int) $id);
            $this->postJson("/api/v1/my/listings/{$id}/submit", ['consent' => true])
                ->assertOk()
                ->assertJsonPath('data.contact_flagged', true)
                ->assertJsonPath('data.contact_flag_reason', $reason);
        }
    }

    public function test_wizard_saves_remaining_ui_fields(): void
    {
        $this->marketplaceUser();
        $refs = $this->seedCatalog();
        $id = $this->postJson('/api/v1/my/listings')->json('data.id');

        $this->putJson("/api/v1/my/listings/{$id}/step/specs", [
            'governorate_id' => $refs['area']->governorate_id,
            'area_id' => $refs['area']->id,
            'unit_type_id' => $refs['unit']->id,
            'title_ar' => 'شقة مفروشة بالنزهة',
            'description_ar' => 'وصف كامل للوحدة بدون أي رقم هاتف.',
            'area_sqm' => 110,
            'bedrooms' => 2,
            'furnished' => 'furnished',
            'handover_date' => '2027-06-01',
            'neighborhood' => 'النزهة الجديدة',
        ])->assertOk()
            ->assertJsonPath('data.furnished', 'furnished')
            ->assertJsonPath('data.handover_date', '2027-06-01')
            ->assertJsonPath('data.neighborhood', 'النزهة الجديدة');

        $this->putJson("/api/v1/my/listings/{$id}/step/financials", [
            'declared_contract_price' => 1000000,
            'declared_contract_date' => '2024-03-15',
            'declared_asking_price' => 950000,
            'declared_payment_system' => 'mix',
            'declared_assignment_restrictions' => 'بعد سداد 50٪',
        ])->assertOk()
            ->assertJsonPath('data.financials.declared_contract_date', '2024-03-15')
            ->assertJsonPath('data.financials.declared_payment_system', 'mix')
            ->assertJsonPath('data.financials.declared_assignment_restrictions', 'بعد سداد 50٪');

        $this->putJson("/api/v1/my/listings/{$id}/step/specs", [
            'furnished' => 'semi',
        ])->assertUnprocessable();
    }

    public function test_public_show_increments_view_count_owner_show_does_not(): void
    {
        $listing = Listing::factory()->published()->create(['view_count' => 0]);

        $this->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonMissingPath('data.view_count');
        $this->assertSame(1, (int) $listing->fresh()->view_count);

        $this->getJson('/api/v1/listings/'.$listing->id)->assertOk();
        $this->assertSame(2, (int) $listing->fresh()->view_count);

        $this->getJson('/api/v1/listings')->assertOk();
        $this->assertSame(2, (int) $listing->fresh()->view_count);

        Sanctum::actingAs($listing->owner);
        $this->getJson('/api/v1/my/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.view_count', 2);
        $this->assertSame(2, (int) $listing->fresh()->view_count);
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
