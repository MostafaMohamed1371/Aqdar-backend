<?php

namespace Tests\Integration;

use App\Modules\Catalog\Models\ContentBlock;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EstimatePhase17Test extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_post_manual_estimate(): void
    {
        $this->postJson('/api/v1/estimates', [
            'cancellation_percent' => 10,
            'total_paid' => 1450000,
        ])->assertUnauthorized();
    }

    public function test_manual_estimate_without_percent_is_not_computable(): void
    {
        $this->marketplaceUser();

        $response = $this->postJson('/api/v1/estimates', [
            'contract_price' => 2900000,
            'total_paid' => 1450000,
            'remaining' => 1450000,
            'asking_price' => 2650000,
        ])
            ->assertOk()
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.listing_id', null)
            ->assertJsonPath('data.computable', false)
            ->assertJsonPath('data.reason', 'cancellation_percent_missing')
            ->assertJsonPath('data.cancellation', null)
            ->assertJsonPath('data.market.label', 'estimate')
            ->assertJsonPath('data.market.certified_valuation', false)
            ->assertJsonPath('data.market.sample_size', 0);

        $inputs = collect($response->json('data.inputs'))->keyBy('key');
        $this->assertFalse($inputs['contract_price']['verified']);
        $this->assertFalse($inputs['cancellation_percent']['verified']);
        $this->assertTrue($inputs['cancellation_percent']['required']);
        $this->assertNotNull($response->json('data.disclaimer.text'));
    }

    public function test_manual_estimate_computes_penalty_from_posted_percent(): void
    {
        $this->marketplaceUser();

        $this->postJson('/api/v1/estimates', [
            'total_paid' => 1450000,
            'cancellation_percent' => 10,
            'asking_price' => 2650000,
        ])
            ->assertOk()
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.computable', true)
            ->assertJsonPath('data.reason', null)
            ->assertJsonPath('data.cancellation.percent', 10)
            ->assertJsonPath('data.cancellation.percent_source', 'manual')
            ->assertJsonPath('data.cancellation.penalty', 145000)
            ->assertJsonPath('data.cancellation.net_recovery', 1305000)
            ->assertJsonPath('data.transfer.asking_price', 2650000);
    }

    public function test_manual_zero_percent_is_computable(): void
    {
        $this->marketplaceUser();

        $this->postJson('/api/v1/estimates', [
            'total_paid' => 1000000,
            'cancellation_percent' => 0,
        ])
            ->assertOk()
            ->assertJsonPath('data.computable', true)
            ->assertJsonPath('data.cancellation.percent', 0)
            ->assertJsonPath('data.cancellation.penalty', 0);
    }

    public function test_manual_estimate_other_users_listing_is_hidden(): void
    {
        $this->marketplaceUser();
        $other = Listing::factory()->create();

        $this->postJson('/api/v1/estimates', [
            'listing_id' => $other->id,
            'cancellation_percent' => 10,
            'total_paid' => 1000000,
        ])->assertNotFound();
    }

    public function test_manual_estimate_can_use_owned_listing_for_market(): void
    {
        $owner = $this->marketplaceUser();
        $listing = Listing::factory()->published()->create(['user_id' => $owner->id]);
        $peer = Listing::factory()->published()->create([
            'area_id' => $listing->area_id,
            'unit_type_id' => $listing->unit_type_id,
        ]);
        $peer->financials?->forceFill(['declared_asking_price' => 3100000, 'asking_price_verified' => false])->save();

        $this->postJson('/api/v1/estimates', [
            'listing_id' => $listing->id,
            'total_paid' => 1450000,
            'cancellation_percent' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.listing_id', $listing->id)
            ->assertJsonPath('data.public_code', $listing->public_code)
            ->assertJsonPath('data.computable', true)
            ->assertJsonPath('data.market.min', 3100000);
    }

    public function test_manual_estimate_market_from_area_and_unit_type(): void
    {
        $this->marketplaceUser();
        $peer = Listing::factory()->published()->create();
        $peer->financials?->forceFill(['declared_asking_price' => 3100000, 'asking_price_verified' => false])->save();
        $otherType = UnitType::factory()->create();
        Listing::factory()->published()->create([
            'area_id' => $peer->area_id,
            'unit_type_id' => $otherType->id,
        ])->financials?->forceFill(['declared_asking_price' => 9000000, 'asking_price_verified' => false])->save();

        $this->postJson('/api/v1/estimates', [
            'area_id' => $peer->area_id,
            'unit_type_id' => $peer->unit_type_id,
            'cancellation_percent' => 10,
            'total_paid' => 1000000,
        ])
            ->assertOk()
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.listing_id', null)
            ->assertJsonPath('data.computable', true)
            ->assertJsonPath('data.market.min', 3100000)
            ->assertJsonPath('data.market.max', 3100000);
    }

    public function test_manual_disclaimer_prefers_cms_copy(): void
    {
        ContentBlock::factory()->create([
            'key' => 'exit_estimate',
            'body_ar' => 'إخلاء يدوي',
            'body_en' => 'Manual CMS disclaimer',
            'is_active' => true,
        ]);
        $this->marketplaceUser();

        $this->withHeaders(['Accept-Language' => 'en'])
            ->postJson('/api/v1/estimates', ['cancellation_percent' => 8, 'total_paid' => 500000])
            ->assertOk()
            ->assertJsonPath('data.disclaimer.source', 'cms')
            ->assertJsonPath('data.disclaimer.text', 'Manual CMS disclaimer');
    }

    private function marketplaceUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
