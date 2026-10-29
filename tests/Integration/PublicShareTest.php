<?php

namespace Tests\Integration;

use App\Modules\Listing\Models\Listing;
use App\Modules\Setting\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class PublicShareTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_published_listing_share_page_hides_seller_contact(): void
    {
        $listing = Listing::factory()->published()->create();
        $listing->owner->forceFill(['phone' => '+201011112222', 'email' => 'seller-share@example.com'])->save();

        $this->get('/property/'.$listing->public_code)
            ->assertOk()
            ->assertSee('APP-40', false)
            ->assertSee($listing->public_code)
            ->assertDontSee('+201011112222')
            ->assertDontSee('seller-share@example.com');

        $this->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.share_url', url('/property/'.$listing->public_code));
    }

    public function test_unpublished_share_page_is_404(): void
    {
        $listing = Listing::factory()->create();

        $this->get('/property/'.$listing->public_code)->assertNotFound();
        $this->get('/property/missing-code')->assertNotFound();
    }

    public function test_share_page_follows_maintenance_mode(): void
    {
        $listing = Listing::factory()->published()->create();
        app(SettingService::class)->put('maintenance_mode', '1', 'platform');

        $this->get('/property/'.$listing->public_code)->assertStatus(503);
        $this->actingAsStaff();
        $this->get('/dashboard')->assertOk();
    }
}
