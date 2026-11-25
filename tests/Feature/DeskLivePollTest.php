<?php

namespace Tests\Feature;

use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Listing\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DeskLivePollTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_desk_live_returns_new_listings_for_review_without_refresh(): void
    {
        $this->actingAsStaff(StaffRole::Operations);

        $older = Listing::factory()->create(['status' => ListingStatus::InReview]);
        $newer = Listing::factory()->create(['status' => ListingStatus::InReview]);

        $this->getJson(route('dashboard.live', ['listings_after' => $older->id]))
            ->assertOk()
            ->assertJsonPath('data.listings.0.id', $newer->id)
            ->assertJsonPath('data.listings.0.public_code', $newer->public_code)
            ->assertJsonPath('data.listings.0.url', route('dashboard.listings.show', $newer))
            ->assertJsonStructure([
                'data' => [
                    'listings',
                    'leads',
                    'requests',
                    'cases',
                    'documents',
                    'badges' => ['ADM-02', 'ADM-03'],
                ],
            ]);

        $this->get(route('dashboard.listings.index'))
            ->assertOk()
            ->assertSee('data-live-table="listings"', false)
            ->assertSee('data-desk-live=', false);
    }

    public function test_guest_cannot_poll_desk_live(): void
    {
        $this->getJson(route('dashboard.live'))->assertUnauthorized();
    }
}
