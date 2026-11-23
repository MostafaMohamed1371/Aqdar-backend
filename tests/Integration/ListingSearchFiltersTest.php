<?php

namespace Tests\Integration;

use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Listing\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingSearchFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_filter_returns_only_that_category(): void
    {
        $match = Listing::factory()->published()->create();
        $other = Listing::factory()->published()->create();

        $this->getJson('/api/v1/listings?category_id='.$match->category_id)
            ->assertOk()
            ->assertJsonFragment(['id' => $match->id])
            ->assertJsonMissing(['id' => $other->id]);
    }

    public function test_property_type_accepts_unit_type_id_or_slug(): void
    {
        $unitType = UnitType::factory()->create(['slug' => 'villa']);
        $match = Listing::factory()->published()->create(['unit_type_id' => $unitType->id]);
        $other = Listing::factory()->published()->create();

        foreach (['villa', (string) $unitType->id] as $value) {
            $this->getJson('/api/v1/listings?property_type='.$value)
                ->assertOk()
                ->assertJsonFragment(['id' => $match->id])
                ->assertJsonMissing(['id' => $other->id]);
        }

        $this->getJson('/api/v1/listings?property_type=unknown-type')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_featured_flag_filters_by_the_featured_tag(): void
    {
        $tag = Tag::factory()->create(['slug' => 'featured']);
        $featured = Listing::factory()->published()->create(['tag_ids' => [$tag->id]]);
        $plain = Listing::factory()->published()->create();

        $this->getJson('/api/v1/listings?featured=true')
            ->assertOk()
            ->assertJsonFragment(['id' => $featured->id])
            ->assertJsonMissing(['id' => $plain->id]);

        $this->getJson('/api/v1/listings?featured=0')
            ->assertOk()
            ->assertJsonFragment(['id' => $plain->id]);
    }

    public function test_bbox_limits_search_and_map_to_the_visible_area(): void
    {
        $cairo = Listing::factory()->published()->create(['lat' => 30.0444, 'lng' => 31.2357]);
        $alex = Listing::factory()->published()->create(['lat' => 31.2001, 'lng' => 29.9187]);

        $cairoBox = 'bbox=31.0,29.9,31.5,30.2';

        $this->getJson("/api/v1/listings?{$cairoBox}")
            ->assertOk()
            ->assertJsonFragment(['id' => $cairo->id])
            ->assertJsonMissing(['id' => $alex->id]);

        $this->getJson("/api/v1/map?{$cairoBox}")
            ->assertOk()
            ->assertJsonFragment(['id' => $cairo->id])
            ->assertJsonMissing(['id' => $alex->id]);
    }

    public function test_invalid_bbox_is_rejected(): void
    {
        foreach (['abc', '1,2,3', '32,30,31,31', '31,30,32,95'] as $bbox) {
            $this->getJson('/api/v1/listings?bbox='.$bbox)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('bbox');
        }
    }
}
