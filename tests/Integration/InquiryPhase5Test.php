<?php

namespace Tests\Integration;

use App\Core\Audit\Models\AccessLog;
use App\Core\Enums\LeadStatus;
use App\Core\Enums\ListingStatus;
use App\Modules\Crm\Models\Inquiry;
use App\Modules\Crm\Models\Lead;
use App\Modules\Listing\Models\Listing;
use App\Modules\Setting\Services\SettingService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InquiryPhase5Test extends TestCase
{
    use RefreshDatabase;

    public function test_inquiry_creates_lead_and_returns_sales_contact(): void
    {
        $this->setSalesPhone('16890');
        $listing = Listing::factory()->published()->create();
        $buyer = $this->marketplaceUser();

        $response = $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'message' => 'مهتم بالمعاينة',
            'contact_method' => 'phone',
            'preferred_time' => 'evening',
            'consent' => true,
        ])->assertCreated()
            ->assertJsonPath('data.sales_contact.phone', '16890')
            ->assertJsonPath('data.status', 'new');

        $code = $response->json('data.public_code');
        $this->assertNotNull($code);
        $this->assertStringStartsWith('INQ-', $code);

        $inquiry = Inquiry::query()->where('public_code', $code)->first();
        $this->assertNotNull($inquiry);
        $this->assertNotNull($inquiry->lead_id);
        $this->assertSame(LeadStatus::New, $inquiry->lead->status);
        $this->assertFalse($inquiry->lead->is_duplicate);
    }

    public function test_changing_sales_phone_in_settings_changes_inquiry_response(): void
    {
        $this->setSalesPhone('19999');
        $listing = Listing::factory()->published()->create();
        $this->marketplaceUser();

        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'whatsapp',
            'consent' => true,
        ])->assertCreated()->assertJsonPath('data.sales_contact.phone', '19999');

        $this->getJson('/api/v1/config/sales-contact')
            ->assertOk()
            ->assertJsonPath('data.phone', '19999')
            ->assertJsonStructure(['data' => ['name', 'phone', 'hours']]);
    }

    public function test_seller_interest_has_no_buyer_pii(): void
    {
        $listing = Listing::factory()->published()->create();
        $buyer = User::factory()->create([
            'name' => 'BuyerSecretNameZX',
            'phone' => '+201012345678',
            'is_buyer' => true,
        ]);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'email',
            'consent' => true,
        ])->assertCreated();

        Sanctum::actingAs($listing->owner);
        $response = $this->getJson('/api/v1/my/listings/'.$listing->id.'/interest')
            ->assertOk()
            ->assertJsonPath('data.0.interested_buyer_seq', 1)
            ->assertJsonStructure(['data' => [['interested_buyer_seq', 'status', 'created_at', 'offer_amount']], 'sales_contact']);

        $payload = $response->getContent();
        $this->assertStringNotContainsString('BuyerSecretNameZX', $payload);
        $this->assertStringNotContainsString('201012345678', $payload);
        $this->assertStringNotContainsString('+201012345678', $payload);
        $this->assertArrayNotHasKey('name', $response->json('data.0'));
        $this->assertArrayNotHasKey('phone', $response->json('data.0'));
        $this->assertArrayNotHasKey('buyer_id', $response->json('data.0'));
    }

    public function test_duplicate_inquiry_flags_open_lead(): void
    {
        $listing = Listing::factory()->published()->create();
        $this->marketplaceUser();

        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();

        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();

        $this->assertSame(1, Lead::query()->count());
        $this->assertSame(2, Inquiry::query()->count());
        $this->assertTrue(Lead::query()->first()->is_duplicate);
    }

    public function test_unpublished_listing_inquiry_is_not_found(): void
    {
        $listing = Listing::factory()->create(['status' => ListingStatus::Draft]);
        $this->marketplaceUser();

        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertNotFound();
    }

    public function test_owner_cannot_inquire_own_listing(): void
    {
        $listing = Listing::factory()->published()->create();
        Sanctum::actingAs($listing->owner);

        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('listing_id');
    }

    public function test_inquiry_message_cannot_contain_phone(): void
    {
        $listing = Listing::factory()->published()->create();
        $this->marketplaceUser();

        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'message' => 'اتصل على 01012345678',
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    public function test_inquiry_requires_consent_and_auth(): void
    {
        $listing = Listing::factory()->published()->create();

        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertUnauthorized();

        $this->marketplaceUser();
        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('consent');
    }

    public function test_buyer_can_list_and_show_own_inquiries(): void
    {
        $listing = Listing::factory()->published()->create();
        $buyer = $this->marketplaceUser();

        $created = $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();

        $id = $created->json('data.id');
        $this->getJson('/api/v1/my/inquiries')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/my/inquiries/'.$id)->assertOk()->assertJsonPath('data.id', $id);

        $stranger = User::factory()->create(['is_buyer' => true]);
        Sanctum::actingAs($stranger);
        $this->getJson('/api/v1/my/inquiries/'.$id)->assertNotFound();
    }

    public function test_appointment_creates_lead_and_returns_sales_contact(): void
    {
        $this->setSalesPhone('16890');
        $listing = Listing::factory()->published()->create();
        $this->marketplaceUser();

        $created = $this->postJson('/api/v1/appointments', [
            'listing_id' => $listing->id,
            'requested_date' => now()->addDay()->toDateString(),
            'requested_slot' => '10:30',
            'notes' => 'صباحاً',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.kind', 'viewing')
            ->assertJsonPath('data.sales_contact.phone', '16890');

        $id = $created->json('data.id');
        $this->assertSame(1, Lead::query()->count());
        $this->assertSame('viewing', Lead::query()->first()->next_action);

        $this->getJson('/api/v1/my/appointments')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/my/appointments/'.$id)->assertOk()->assertJsonPath('data.id', $id);
    }

    public function test_inquiry_includes_listing_card_type_and_next_steps(): void
    {
        $listing = Listing::factory()->published()->create();
        $this->marketplaceUser();

        $response = $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'inquiry_type' => 'interested',
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'listing_inquiry')
            ->assertJsonPath('data.inquiry_type', 'interested')
            ->assertJsonPath('data.listing.id', $listing->id)
            ->assertJsonStructure(['data' => ['listing' => ['id', 'public_code', 'title', 'photo'], 'next_steps']]);

        $this->assertCount(3, $response->json('data.next_steps'));
    }

    public function test_buyer_can_filter_inquiries_and_request_callback(): void
    {
        $listing = Listing::factory()->published()->create();
        $this->marketplaceUser();

        $created = $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();
        $id = $created->json('data.id');

        $this->getJson('/api/v1/my/inquiries?status=new')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/my/inquiries?status=closed')->assertOk()->assertJsonCount(0, 'data');

        $this->postJson('/api/v1/my/inquiries/'.$id.'/callback')->assertOk();
        $this->assertSame('callback', Inquiry::query()->find($id)?->lead?->next_action);
    }

    public function test_seller_interest_includes_kind_client_status_and_counts(): void
    {
        $listing = Listing::factory()->published()->create();
        $buyer = User::factory()->create([
            'name' => 'BuyerSecretNameZX',
            'phone' => '+201012345678',
            'is_buyer' => true,
        ]);
        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'email',
            'consent' => true,
        ])->assertCreated();

        Sanctum::actingAs($listing->owner);
        $this->getJson('/api/v1/my/listings/'.$listing->id.'/interest')
            ->assertOk()
            ->assertJsonPath('data.0.kind', 'listing_inquiry')
            ->assertJsonPath('data.0.client_status', 'new')
            ->assertJsonPath('counts.all', 1)
            ->assertJsonPath('counts.new', 1)
            ->assertJsonPath('counts.closed', 0);
    }

    public function test_new_inquiry_after_converted_lead_sets_duplicate_of_id(): void
    {
        $listing = Listing::factory()->published()->create();
        $this->marketplaceUser();
        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();

        $closed = Lead::query()->first();
        $closed->forceFill(['status' => LeadStatus::Converted])->save();

        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();

        $this->assertSame(2, Lead::query()->count());
        $fresh = Lead::query()->latest('id')->first();
        $this->assertTrue($fresh->is_duplicate);
        $this->assertSame($closed->id, $fresh->duplicate_of_id);
    }

    private function setSalesPhone(string $phone): void
    {
        app(SettingService::class)->create([
            'key' => 'sales_phone',
            'value' => $phone,
            'group' => 'sales',
        ]);
    }

    private function marketplaceUser(): User
    {
        $user = User::factory()->create(['is_seller' => false, 'is_buyer' => true]);
        Sanctum::actingAs($user);

        return $user;
    }
}
