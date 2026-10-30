<?php

namespace Tests\Integration;

use App\Core\Enums\ListingStatus;
use App\Core\Enums\MarketSegment;
use App\Modules\Category\Models\Category;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class PrivacyNegativeSuiteTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_uat_05_unpublished_listing_is_404_to_guests(): void
    {
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);

        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
        $this->getJson('/api/v1/listings/'.$listing->id.'/media')->assertNotFound();
        $this->get('/property/'.$listing->public_code)->assertNotFound();
    }

    public function test_uat_03_non_distressed_listing_shows_segment_and_approved_public_fields(): void
    {
        $category = Category::query()->where('slug', 'non-distressed')->first()
            ?? Category::factory()->create(['slug' => 'non-distressed-uat03']);
        $listing = Listing::factory()->published()->create([
            'segment' => MarketSegment::NonDistressed,
            'distressed_child' => null,
            'category_id' => $category->id,
        ]);
        $listing->financials()->update([
            'declared_asking_price' => 2400000,
            'verified_asking_price' => 2300000,
            'asking_price_verified' => true,
            'declared_contract_price' => 2000000,
        ]);

        $this->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.segment', 'non_distressed')
            ->assertJsonPath('data.distressed_child', null)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.asking_price', 2300000)
            ->assertJsonPath('data.asking_price_source', 'verified')
            ->assertJsonPath('data.verification_badge', true)
            ->assertJsonPath('data.currency', 'EGP')
            ->assertJsonMissingPath('data.unit_code')
            ->assertJsonMissingPath('data.commission_policy_code');

        $this->getJson('/api/v1/listings?segment=non_distressed')
            ->assertOk()
            ->assertJsonPath('data.0.id', $listing->id);
        $this->getJson('/api/v1/listings?segment=distressed')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_uat_08_public_listing_and_share_hide_seller_pii(): void
    {
        $owner = User::factory()->seller()->create([
            'phone' => '+201055556666',
            'email' => 'owner-secret@example.com',
        ]);
        $listing = Listing::factory()->published()->create(['user_id' => $owner->id]);

        $response = $this->getJson('/api/v1/listings/'.$listing->id)->assertOk();
        $encoded = (string) json_encode($response->json());

        $response->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.phone')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.documents_meta');
        $this->assertStringNotContainsString('+201055556666', $encoded);
        $this->assertStringNotContainsString('owner-secret@example.com', $encoded);
        $this->assertStringNotContainsString('"user_id"', $encoded);

        $this->get('/property/'.$listing->public_code)
            ->assertOk()
            ->assertDontSee('+201055556666')
            ->assertDontSee('owner-secret@example.com');
    }

    public function test_uat_17_default_csv_omits_phones_and_email(): void
    {
        $this->actingAsStaff();
        $listing = Listing::factory()->published()->create();
        $listing->owner->forceFill([
            'phone' => '+201011112222',
            'email' => 'hidden-export@example.com',
        ])->save();

        $body = $this->get('/dashboard/reports/export?type=listings&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->streamedContent();

        $this->assertStringNotContainsString('+201011112222', $body);
        $this->assertStringNotContainsString('owner_phone', $body);
        $this->assertStringNotContainsString('hidden-export@example.com', $body);
    }

    public function test_public_config_does_not_expose_secrets(): void
    {
        $json = $this->getJson('/api/v1/config')->assertOk()->json('data');

        $this->assertArrayNotHasKey('sales_name_en', $json);
        $encoded = (string) json_encode($json);
        $this->assertStringNotContainsString('AQDAR_SMS_HTTP_TOKEN', $encoded);
        $this->assertStringNotContainsString('private_key', $encoded);
    }

    public function test_seller_case_payload_hides_buyer_pii(): void
    {
        $case = TransferCase::factory()->create();
        $buyer = $case->buyer;
        $this->assertNotNull($buyer);

        Sanctum::actingAs($case->seller);
        $encoded = (string) json_encode($this->getJson('/api/v1/my/cases/'.$case->id)->assertOk()->json());
        $this->assertStringNotContainsString((string) $buyer->name, $encoded);
        $this->assertStringNotContainsString((string) $buyer->phone, $encoded);
        $this->assertStringNotContainsString((string) $buyer->email, $encoded);
        $this->assertStringNotContainsString('internal_stage', $encoded);
        $this->assertStringNotContainsString('new_client', $encoded);
    }
}
