<?php

namespace Tests\Integration;

use App\Core\Enums\BuyerRequestStatus;
use App\Core\Enums\ListingStatus;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\RequestMatch;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuyerRequestPhase6Test extends TestCase
{
    use RefreshDatabase;

    public function test_custom_request_with_zero_matches_is_created(): void
    {
        $this->marketplaceUser();

        $response = $this->postJson('/api/v1/buyer-requests', [
            'intent' => 'living',
            'contact_method' => 'phone',
            'consent' => true,
            'budget_max' => 3500000,
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'buyer_request')
            ->assertJsonPath('data.status', 'in_review');

        $code = $response->json('data.public_code');
        $this->assertNotNull($code);
        $this->assertStringStartsWith('BR-', $code);
        $this->assertSame([], $response->json('data.options'));
        $this->assertArrayNotHasKey('internal_status', $response->json('data'));
        $this->assertSame(1, BuyerRequest::query()->count());
        $this->assertSame(1, Lead::query()->count());
    }

    public function test_guest_cannot_create_buyer_request(): void
    {
        $this->postJson('/api/v1/buyer-requests', [
            'intent' => 'living',
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertUnauthorized();
    }

    public function test_buyer_request_requires_consent(): void
    {
        $this->marketplaceUser();
        $this->postJson('/api/v1/buyer-requests', [
            'intent' => 'living',
            'contact_method' => 'phone',
            'consent' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('consent');
    }

    public function test_priorities_cannot_contain_phone(): void
    {
        $this->marketplaceUser();
        $this->postJson('/api/v1/buyer-requests', [
            'intent' => 'living',
            'contact_method' => 'phone',
            'consent' => true,
            'priorities' => 'اتصل على 01012345678',
        ])->assertUnprocessable()->assertJsonValidationErrors('priorities');
    }

    public function test_buyer_can_list_show_and_callback_own_request(): void
    {
        $buyer = $this->marketplaceUser();
        $created = $this->postJson('/api/v1/buyer-requests', [
            'intent' => 'investment',
            'contact_method' => 'whatsapp',
            'consent' => true,
        ])->assertCreated();
        $id = $created->json('data.id');

        $this->getJson('/api/v1/my/buyer-requests')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/my/buyer-requests/'.$id)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonMissingPath('data.internal_status');

        $this->postJson('/api/v1/my/buyer-requests/'.$id.'/callback')->assertOk();
        $this->assertSame('callback', BuyerRequest::query()->find($id)?->lead?->next_action);

        $stranger = User::factory()->create(['is_buyer' => true]);
        Sanctum::actingAs($stranger);
        $this->getJson('/api/v1/my/buyer-requests/'.$id)->assertNotFound();
        $this->postJson('/api/v1/my/buyer-requests/'.$id.'/callback')->assertNotFound();
    }

    public function test_unsent_matches_are_hidden_from_the_buyer(): void
    {
        $buyer = $this->marketplaceUser();
        $request = BuyerRequest::factory()->create(['user_id' => $buyer->id]);
        RequestMatch::factory()->create([
            'buyer_request_id' => $request->id,
            'sent_at' => null,
            'score' => 90,
        ]);

        $this->getJson('/api/v1/my/buyer-requests/'.$request->id)
            ->assertOk()
            ->assertJsonPath('data.options', []);
    }

    public function test_inquiries_inbox_mixes_buyer_requests_and_cases(): void
    {
        $listing = Listing::factory()->published()->create();
        $buyer = $this->marketplaceUser();

        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();

        $this->postJson('/api/v1/buyer-requests', [
            'intent' => 'living',
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();

        $case = TransferCase::factory()->create(['buyer_id' => $buyer->id]);

        $kinds = collect($this->getJson('/api/v1/my/inquiries')->assertOk()->json('data'))
            ->pluck('kind')
            ->all();
        $this->assertContains('listing_inquiry', $kinds);
        $this->assertContains('buyer_request', $kinds);
        $this->assertContains('case', $kinds);

        $this->getJson('/api/v1/my/cases/'.$case->id)
            ->assertOk()
            ->assertJsonPath('data.kind', 'case')
            ->assertJsonPath('data.status', 'interest_received')
            ->assertJsonMissingPath('data.internal_stage');

        $keys = collect($this->getJson('/api/v1/my/cases/'.$case->id)->json('data.timeline'))->pluck('key')->all();
        $this->assertContains('interest_received', $keys);
        $this->assertNotContains('new_client', $keys);

        $other = User::factory()->create(['is_buyer' => true]);
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/my/cases/'.$case->id)->assertNotFound();
    }

    public function test_matching_criteria_are_persisted_when_score_meets_threshold(): void
    {
        $listing = Listing::factory()->published()->create([
            'bedrooms' => 3,
            'area_sqm' => 120,
            'finishing' => 'finished',
        ]);
        $buyer = $this->marketplaceUser();

        $created = $this->postJson('/api/v1/buyer-requests', [
            'intent' => 'living',
            'contact_method' => 'phone',
            'consent' => true,
            'unit_type_id' => $listing->unit_type_id,
            'governorate_id' => $listing->governorate_id,
            'area_id' => $listing->area_id,
            'bedrooms' => 3,
            'area_sqm_min' => 100,
            'area_sqm_max' => 160,
            'budget_min' => 1000000,
            'budget_max' => 3000000,
            'finishing' => 'finished',
            'timeline' => '3_months',
            'down_payment' => 400000,
        ])->assertCreated();

        $id = $created->json('data.id');
        $match = RequestMatch::query()->where('buyer_request_id', $id)->first();
        $this->assertNotNull($match);
        $this->assertGreaterThanOrEqual(40, (int) $match->score);
        $this->assertSame([], $created->json('data.options'));

        $show = $this->getJson('/api/v1/my/buyer-requests/'.$id)->assertOk();
        $this->assertSame($listing->unitType?->localizedName(), $show->json('data.unit_type'));
        $this->assertSame($listing->governorate?->localizedName(), $show->json('data.governorate'));
        $this->assertSame($listing->area?->localizedName(), $show->json('data.area'));
        $this->assertSame('3_months', $show->json('data.purchase_timeline'));
        $this->assertEquals(400000, $show->json('data.down_payment'));
        $this->assertIsArray($show->json('data.timeline'));
    }

    public function test_sent_match_hides_owner_pii_from_the_buyer(): void
    {
        $buyer = $this->marketplaceUser();
        $listing = Listing::factory()->published()->create();
        $owner = $listing->owner;
        $request = BuyerRequest::factory()->create(['user_id' => $buyer->id]);
        RequestMatch::factory()->create([
            'buyer_request_id' => $request->id,
            'listing_id' => $listing->id,
            'sent_at' => now(),
            'score' => 91,
        ]);

        $response = $this->getJson('/api/v1/my/buyer-requests/'.$request->id)->assertOk();
        $listingCard = $response->json('data.options.0.listing');
        $this->assertSame(['id', 'public_code', 'title', 'photo'], array_keys($listingCard));
        $payload = $response->json('data');
        $this->assertArrayNotHasKey('owner', $payload);
        $this->assertArrayNotHasKey('seller', $payload);
        $encoded = $response->getContent();
        $this->assertStringNotContainsString((string) $owner?->phone, $encoded);
        $this->assertStringNotContainsString((string) $owner?->email, $encoded);
        $this->assertStringNotContainsString((string) $owner?->name, $encoded);
    }

    public function test_publishing_a_listing_rematches_open_buyer_requests(): void
    {
        $listing = Listing::factory()->create([
            'status' => ListingStatus::Draft,
            'bedrooms' => 3,
            'area_sqm' => 120,
            'finishing' => 'finished',
        ]);
        $buyer = $this->marketplaceUser();
        $request = BuyerRequest::factory()->create([
            'user_id' => $buyer->id,
            'status' => BuyerRequestStatus::New,
            'unit_type_id' => $listing->unit_type_id,
            'governorate_id' => $listing->governorate_id,
            'area_id' => $listing->area_id,
            'bedrooms' => 3,
            'area_sqm_min' => 100,
            'area_sqm_max' => 160,
            'budget_min' => 1000000,
            'budget_max' => 3000000,
            'finishing' => 'finished',
        ]);

        $this->assertSame(0, RequestMatch::query()->where('buyer_request_id', $request->id)->count());

        $listing->forceFill([
            'status' => ListingStatus::Published,
            'published_at' => now(),
        ])->save();

        $this->assertTrue(
            RequestMatch::query()
                ->where('buyer_request_id', $request->id)
                ->where('listing_id', $listing->id)
                ->exists()
        );
    }

    public function test_closed_no_match_maps_to_client_closed_chip(): void
    {
        $buyer = $this->marketplaceUser();
        $request = BuyerRequest::factory()->create([
            'user_id' => $buyer->id,
            'status' => BuyerRequestStatus::ClosedNoMatch,
        ]);

        $this->getJson('/api/v1/my/buyer-requests/'.$request->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonMissingPath('data.internal_status');
    }

    public function test_buyer_can_list_own_cases(): void
    {
        $buyer = $this->marketplaceUser();
        $mine = TransferCase::factory()->create(['buyer_id' => $buyer->id]);
        TransferCase::factory()->create();

        $this->getJson('/api/v1/my/cases')
            ->assertOk()
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.status', 'interest_received')
            ->assertJsonMissingPath('data.0.internal_stage');
        $this->assertCount(1, $this->getJson('/api/v1/my/cases')->json('data'));
    }

    public function test_inbox_in_review_includes_open_cases_with_client_label(): void
    {
        $buyer = $this->marketplaceUser();
        $case = TransferCase::factory()->create(['buyer_id' => $buyer->id]);

        $row = collect($this->getJson('/api/v1/my/inquiries?status=in_review')->assertOk()->json('data'))
            ->firstWhere('kind', 'case');

        $this->assertNotNull($row);
        $this->assertSame($case->id, $row['id']);
        $this->assertSame('in_review', $row['status']);
        $this->assertSame($case->client_label, $row['label']);
    }

    public function test_buyer_can_upload_requested_case_document(): void
    {
        Storage::fake('private_documents');
        $buyer = $this->marketplaceUser();
        $case = TransferCase::factory()->create([
            'buyer_id' => $buyer->id,
            'missing_documents_count' => 1,
            'requested_document_note' => 'عقد',
        ]);

        $this->post('/api/v1/my/cases/'.$case->id.'/documents', [
            'kind' => 'buyer_requested',
            'file' => UploadedFile::fake()->create('buyer-doc.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(0, (int) $case->fresh()->missing_documents_count);
        $this->assertNull($case->fresh()->requested_document_note);
    }

    private function marketplaceUser(): User
    {
        $user = User::factory()->create(['is_seller' => false, 'is_buyer' => true]);
        Sanctum::actingAs($user);

        return $user;
    }
}
