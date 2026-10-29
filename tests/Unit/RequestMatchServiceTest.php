<?php

namespace Tests\Unit;

use App\Core\Enums\SegmentPreference;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\RequestMatch;
use App\Modules\Crm\Services\RequestMatchService;
use App\Modules\Listing\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_catalog_fields_score_one_hundred_and_persist(): void
    {
        [$listing, $unitA] = $this->publishedListing();
        $request = BuyerRequest::factory()->create($this->blankCriteria([
            'unit_type_id' => $unitA->id,
            'governorate_id' => $listing->governorate_id,
            'area_id' => $listing->area_id,
        ]));

        $matcher = app(RequestMatchService::class);
        $this->assertSame(100, $matcher->score($request, $listing->load('financials')));

        $matcher->rematch($request);
        $this->assertTrue(
            RequestMatch::query()
                ->where('buyer_request_id', $request->id)
                ->where('listing_id', $listing->id)
                ->exists()
        );
    }

    public function test_score_below_forty_is_not_persisted(): void
    {
        [$listing] = $this->publishedListing();
        $otherType = UnitType::factory()->create();
        $request = BuyerRequest::factory()->create($this->blankCriteria([
            'unit_type_id' => $otherType->id,
            'finishing' => 'finished',
        ]));

        $matcher = app(RequestMatchService::class);
        $this->assertSame(20, $matcher->score($request, $listing->load('financials')));

        $matcher->rematch($request);
        $this->assertFalse(
            RequestMatch::query()
                ->where('buyer_request_id', $request->id)
                ->where('listing_id', $listing->id)
                ->exists()
        );
    }

    /** @return array{0: Listing, 1: UnitType} */
    private function publishedListing(): array
    {
        $unit = UnitType::factory()->create();
        $governorate = Governorate::factory()->create();
        $area = Area::factory()->create(['governorate_id' => $governorate->id]);
        $listing = Listing::factory()->published()->create([
            'unit_type_id' => $unit->id,
            'governorate_id' => $governorate->id,
            'area_id' => $area->id,
            'bedrooms' => 3,
            'area_sqm' => 120,
            'finishing' => 'finished',
        ]);

        return [$listing, $unit];
    }

    /** @param array<string, mixed> $overrides */
    private function blankCriteria(array $overrides): array
    {
        return array_merge([
            'unit_type_id' => null,
            'governorate_id' => null,
            'area_id' => null,
            'developer_id' => null,
            'project_id' => null,
            'bedrooms' => null,
            'area_sqm_min' => null,
            'area_sqm_max' => null,
            'budget_min' => null,
            'budget_max' => null,
            'finishing' => null,
            'segment_preference' => SegmentPreference::Any,
        ], $overrides);
    }
}
