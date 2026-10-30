<?php

namespace Tests\Integration;

use App\Core\Enums\LeadStatus;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\Inquiry;
use App\Modules\Crm\Models\Lead;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class SellerBuyerTrackingTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_my_listings_meta_has_status_tab_counts(): void
    {
        $seller = User::factory()->create(['is_seller' => true]);
        Listing::factory()->create(['user_id' => $seller->id, 'status' => ListingStatus::Draft]);
        Listing::factory()->create(['user_id' => $seller->id, 'status' => ListingStatus::InReview]);
        Listing::factory()->create(['user_id' => $seller->id, 'status' => ListingStatus::Approved]);
        Listing::factory()->published()->create(['user_id' => $seller->id]);
        Listing::factory()->create(['user_id' => $seller->id, 'status' => ListingStatus::Archived]);
        Listing::factory()->published()->create();
        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/my/listings?status=draft')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('counts', [
                'all' => 5,
                'draft' => 1,
                'review' => 2,
                'needs_changes' => 0,
                'live' => 1,
                'rejected' => 0,
                'ended' => 1,
            ]);

        $this->getJson('/api/v1/my/listings?status=ended')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_seller_sees_anonymous_interest_across_all_listings(): void
    {
        $seller = User::factory()->create(['is_seller' => true]);
        $first = Listing::factory()->published()->create(['user_id' => $seller->id]);
        $second = Listing::factory()->published()->create(['user_id' => $seller->id]);
        $buyer = User::factory()->create(['name' => 'HiddenBuyerName']);
        Lead::factory()->create(['listing_id' => $first->id, 'buyer_id' => $buyer->id, 'status' => LeadStatus::New]);
        Lead::factory()->create(['listing_id' => $second->id, 'buyer_id' => $buyer->id, 'status' => LeadStatus::Contacted]);
        Lead::factory()->create(['listing_id' => Listing::factory()->published()->create()->id]);
        Sanctum::actingAs($seller);

        $response = $this->getJson('/api/v1/my/interest')
            ->assertOk()
            ->assertJsonPath('counts.all', 2)
            ->assertJsonPath('counts.new', 1)
            ->assertJsonPath('counts.in_review', 1)
            ->assertJsonMissing(['HiddenBuyerName']);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            collect($response->json('data'))->pluck('listing.id')->all(),
        );
        $this->assertSame([1, 1], collect($response->json('data'))->pluck('interested_buyer_seq')->all());
    }

    public function test_inquiry_detail_shows_timeline_and_assigned_consultant(): void
    {
        $buyer = User::factory()->create(['is_buyer' => true]);
        $advisor = $this->makeStaff(StaffRole::Sales, ['name' => 'Ahmed Advisor']);
        $lead = Lead::factory()->create([
            'buyer_id' => $buyer->id,
            'assigned_to' => $advisor->id,
            'status' => LeadStatus::Contacted,
            'last_contacted_at' => now(),
        ]);
        $inquiry = Inquiry::factory()->create([
            'user_id' => $buyer->id,
            'listing_id' => $lead->listing_id,
            'lead_id' => $lead->id,
        ]);
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/my/inquiries/'.$inquiry->id)
            ->assertOk()
            ->assertJsonPath('data.consultant.name', 'Ahmed Advisor')
            ->assertJsonPath('data.timeline.0.key', 'received')
            ->assertJsonPath('data.timeline.0.state', 'done')
            ->assertJsonPath('data.timeline.1.state', 'done')
            ->assertJsonPath('data.timeline.2.key', 'contacted')
            ->assertJsonPath('data.timeline.2.state', 'current')
            ->assertJsonPath('data.timeline.4.state', 'future')
            ->assertJsonMissing(['email' => $advisor->email]);
    }

    public function test_unassigned_inquiry_has_no_consultant_yet(): void
    {
        $buyer = User::factory()->create(['is_buyer' => true]);
        $lead = Lead::factory()->create(['buyer_id' => $buyer->id, 'assigned_to' => null, 'status' => LeadStatus::New]);
        $inquiry = Inquiry::factory()->create(['user_id' => $buyer->id, 'listing_id' => $lead->listing_id, 'lead_id' => $lead->id]);
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/my/inquiries/'.$inquiry->id)
            ->assertOk()
            ->assertJsonPath('data.consultant', null)
            ->assertJsonPath('data.timeline.0.state', 'current');
    }
}
