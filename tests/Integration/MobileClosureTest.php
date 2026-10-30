<?php

namespace Tests\Integration;

use App\Core\Enums\ContentBlockType;
use App\Modules\Catalog\Models\ContentBlock;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Crm\Models\Appointment;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use App\Modules\Listing\Models\ListingMedia;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_recently_viewed_lists_public_listings_newest_first_and_can_be_cleared(): void
    {
        $user = User::factory()->create();
        $first = Listing::factory()->published()->create();
        $second = Listing::factory()->published()->create();
        $hidden = Listing::factory()->published()->create();
        Sanctum::actingAs($user);

        $this->travel(-2)->minutes();
        $this->getJson("/api/v1/listings/{$first->id}")->assertOk();
        $this->getJson("/api/v1/listings/{$hidden->id}")->assertOk();
        $this->travelBack();
        $this->getJson("/api/v1/listings/{$second->id}")->assertOk();
        $hidden->forceFill(['status' => 'archived'])->save();

        $this->getJson('/api/v1/recently-viewed')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);

        $this->deleteJson('/api/v1/recently-viewed')->assertOk();
        $this->getJson('/api/v1/recently-viewed')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guest_views_are_not_recorded(): void
    {
        $listing = Listing::factory()->published()->create();

        $this->getJson("/api/v1/listings/{$listing->id}")->assertOk();

        $this->assertDatabaseCount('listing_views', 0);
        $this->getJson('/api/v1/recently-viewed')->assertUnauthorized();
    }

    public function test_owner_reorders_media_and_must_send_every_item(): void
    {
        $listing = Listing::factory()->create();
        [$a, $b, $c] = ListingMedia::factory()->count(3)->sequence(
            ['sort_order' => 1],
            ['sort_order' => 2],
            ['sort_order' => 3],
        )->create(['listing_id' => $listing->id]);
        Sanctum::actingAs($listing->owner);

        $this->putJson("/api/v1/my/listings/{$listing->id}/media/order", ['ids' => [$c->id, $a->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');

        $this->putJson("/api/v1/my/listings/{$listing->id}/media/order", ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $c->id);

        $this->assertSame(1, $c->fresh()->sort_order);
        $this->assertSame(3, $b->fresh()->sort_order);

        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/v1/my/listings/{$listing->id}/media/order", ['ids' => [$a->id, $b->id, $c->id]])
            ->assertNotFound();
    }

    public function test_activity_feed_includes_viewing_appointments(): void
    {
        $appointment = Appointment::factory()->create();
        Sanctum::actingAs($appointment->buyer);

        $this->getJson('/api/v1/my/inquiries')
            ->assertOk()
            ->assertJsonPath('data.0.kind', 'appointment')
            ->assertJsonPath('data.0.status', 'in_review')
            ->assertJsonPath('data.0.requested_slot', $appointment->requested_slot);
    }

    public function test_changing_an_existing_email_is_rejected_not_ignored(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me', ['email' => 'other@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->patchJson('/api/v1/me', ['email' => 'Owner@example.com', 'name' => 'Owner'])->assertOk();
    }

    public function test_counts_include_tags_and_content_has_onboarding_and_seller_guide(): void
    {
        $tag = Tag::factory()->create(['is_active' => true]);
        Listing::factory()->published()->create(['tag_ids' => [$tag->id]]);
        ContentBlock::factory()->create(['type' => ContentBlockType::Onboarding, 'title_en' => 'Welcome']);
        ContentBlock::factory()->create(['type' => ContentBlockType::SellerGuide, 'title_en' => 'How selling works']);

        $this->getJson('/api/v1/listings/counts')
            ->assertOk()
            ->assertJsonPath('data.tags.0.id', $tag->id)
            ->assertJsonPath('data.tags.0.count', 1);

        $this->getJson('/api/v1/content/onboarding')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/content/seller-guide')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_document_filename_with_contact_details_is_stored_generically(): void
    {
        Storage::fake('private_documents');
        $listing = Listing::factory()->create();
        Sanctum::actingAs($listing->owner);

        $this->post("/api/v1/my/listings/{$listing->id}/documents", [
            'type' => 'contract',
            'file' => UploadedFile::fake()->create('contract 01012345678.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame('contract-v1.pdf', ListingDocument::query()->sole()->original_name);
    }
}
