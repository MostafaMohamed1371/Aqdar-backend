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
        $this->assertSame('listing', $payload['source']);
    }

    public function test_manual_missing_percent_never_invents_a_penalty(): void
    {
        $user = Listing::factory()->create()->owner;
        $payload = app(EstimateService::class)->fromManual($user, [
            'total_paid' => 1450000,
            'asking_price' => 2650000,
        ]);

        $this->assertSame('manual', $payload['source']);
        $this->assertFalse($payload['computable']);
        $this->assertSame('cancellation_percent_missing', $payload['reason']);
        $this->assertNull($payload['cancellation']);
        $this->assertFalse($payload['inputs'][3]['verified']);
    }
}
