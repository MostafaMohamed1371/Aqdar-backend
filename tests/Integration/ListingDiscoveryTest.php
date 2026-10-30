<?php

namespace Tests\Integration;

use App\Modules\Catalog\Models\UnitType;
use App\Modules\Listing\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_filter_and_sort_use_the_verified_price_buyers_see(): void
    {
        $corrected = $this->publishedAt(declared: 2_000_000, verified: 3_500_000);
        $declaredOnly = $this->publishedAt(declared: 3_000_000);

        $this->getJson('/api/v1/listings?price_max=2500000')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/listings?price_min=3200000')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $corrected->id)
            ->assertJsonPath('data.0.asking_price', 3500000)
            ->assertJsonPath('data.0.price_badge', 'verified');

        $this->getJson('/api/v1/listings?sort=price_asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $declaredOnly->id)
            ->assertJsonPath('data.0.price_badge', 'estimated');
    }

    public function test_neighborhood_filter_is_case_insensitive(): void
    {
        $match = Listing::factory()->published()->create(['neighborhood' => 'Al Narges 3']);
        Listing::factory()->published()->create(['neighborhood' => 'Al Yasmeen']);

        $this->getJson('/api/v1/listings?neighborhood=al narges 3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_counts_include_published_listings_per_unit_type(): void
    {
        $villa = UnitType::factory()->create(['slug' => 'villa']);
        Listing::factory()->published()->count(2)->create(['unit_type_id' => $villa->id]);
        Listing::factory()->create(['unit_type_id' => $villa->id]);

        $row = collect($this->getJson('/api/v1/listings/counts')->assertOk()->json('data.unit_types'))
            ->firstWhere('id', $villa->id);

        $this->assertSame(['id' => $villa->id, 'slug' => 'villa', 'count' => 2], $row);
    }

    public function test_compare_returns_only_public_listings_in_requested_order(): void
    {
        $first = Listing::factory()->published()->create();
        $second = Listing::factory()->published()->create();
        $draft = Listing::factory()->create();

        $this->getJson('/api/v1/listings/compare?ids='.$second->id.','.$first->id.','.$draft->id)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonStructure(['data' => [['amenities', 'financial_summary', 'area_sqm']]]);
    }

    public function test_bedrooms_and_finishing_filters_match_listing_specs(): void
    {
        $match = Listing::factory()->published()->create(['bedrooms' => 3, 'finishing' => 'Finished']);
        Listing::factory()->published()->create(['bedrooms' => 2, 'finishing' => 'core']);

        $this->getJson('/api/v1/listings?bedrooms=3&finishing=finished')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_compare_needs_two_to_three_listings(): void
    {
        $this->getJson('/api/v1/listings/compare?ids=1')->assertUnprocessable();
        $this->getJson('/api/v1/listings/compare?ids=1,2,3,4')->assertUnprocessable();
    }

    private function publishedAt(float $declared, ?float $verified = null): Listing
    {
        $listing = Listing::factory()->published()->create();
        $listing->financials->forceFill([
            'declared_asking_price' => $declared,
            'verified_asking_price' => $verified,
            'asking_price_verified' => $verified !== null,
        ])->save();

        return $listing;
    }
}
