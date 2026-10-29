<?php

namespace Tests\Unit;

use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Services\EstimateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstimateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_percent_never_invents_a_penalty(): void
    {
        $listing = Listing::factory()->create();
        $owner = $listing->owner;
        $payload = app(EstimateService::class)->forListing($listing, $owner);

        $this->assertFalse($payload['computable']);
        $this->assertSame('cancellation_percent_missing', $payload['reason']);
        $this->assertNull($payload['cancellation']);
    }
}
