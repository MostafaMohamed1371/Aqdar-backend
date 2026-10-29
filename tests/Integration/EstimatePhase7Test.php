<?php

namespace Tests\Integration;

use App\Modules\Catalog\Models\ContentBlock;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EstimatePhase7Test extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_estimate(): void
    {
        $listing = Listing::factory()->create();

        $this->getJson('/api/v1/listings/'.$listing->id.'/estimate')->assertUnauthorized();
    }

    public function test_estimate_without_cancellation_percent_is_not_computable(): void
    {
        $owner = $this->seller();
        $listing = Listing::factory()->create(['user_id' => $owner->id]);
        $listing->financials?->forceFill([
            'declared_cancellation_percent' => null,
            'verified_cancellation_percent' => null,
            'cancellation_percent_verified' => false,
        ])->save();

        $response = $this->getJson('/api/v1/listings/'.$listing->id.'/estimate')
            ->assertOk()
            ->assertJsonPath('data.computable', false)
            ->assertJsonPath('data.reason', 'cancellation_percent_missing')
            ->assertJsonPath('data.cancellation', null);

        $keys = collect($response->json('data.inputs'))->pluck('key')->all();
        $this->assertContains('contract_price', $keys);
        $this->assertContains('cancellation_percent', $keys);
        $this->assertNotNull($response->json('data.disclaimer.text'));
        $this->assertSame('fallback', $response->json('data.disclaimer.source'));
        $this->assertSame('estimate', $response->json('data.market.label'));
        $this->assertFalse($response->json('data.market.certified_valuation'));
        $inputs = collect($response->json('data.inputs'))->keyBy('key');
        $this->assertTrue($inputs['cancellation_percent']['required']);
        $this->assertFalse($inputs['cancellation_percent']['verified']);
        $this->assertArrayHasKey('verified', $inputs['contract_price']);
    }

    public function test_estimate_uses_provided_percent_and_never_invents_one(): void
    {
        $owner = $this->seller();
        $listing = Listing::factory()->create(['user_id' => $owner->id]);
        $listing->financials?->forceFill([
            'declared_total_paid' => 1450000,
            'declared_cancellation_percent' => 10,
            'verified_cancellation_percent' => null,
            'cancellation_percent_verified' => false,
        ])->save();

        $response = $this->getJson('/api/v1/listings/'.$listing->id.'/estimate')
            ->assertOk()
            ->assertJsonPath('data.computable', true)
            ->assertJsonPath('data.reason', null)
            ->assertJsonPath('data.cancellation.percent', 10)
            ->assertJsonPath('data.cancellation.percent_source', 'declared')
            ->assertJsonPath('data.cancellation.penalty', 145000)
            ->assertJsonPath('data.cancellation.net_recovery', 1305000);
    }

    public function test_verified_cancellation_percent_wins_over_declared(): void
    {
        $owner = $this->seller();
        $listing = Listing::factory()->create(['user_id' => $owner->id]);
        $listing->financials?->forceFill([
            'declared_total_paid' => 1000000,
            'declared_cancellation_percent' => 50,
            'verified_cancellation_percent' => 5,
            'cancellation_percent_verified' => true,
        ])->save();

        $this->getJson('/api/v1/listings/'.$listing->id.'/estimate')
            ->assertOk()
            ->assertJsonPath('data.computable', true)
            ->assertJsonPath('data.cancellation.percent', 5)
            ->assertJsonPath('data.cancellation.percent_source', 'verified')
            ->assertJsonPath('data.cancellation.penalty', 50000);
    }

    public function test_other_user_cannot_see_seller_estimate(): void
    {
        $owner = User::factory()->seller()->create();
        $listing = Listing::factory()->published()->create(['user_id' => $owner->id]);
        $this->seller();

        $this->getJson('/api/v1/listings/'.$listing->id.'/estimate')->assertNotFound();
    }

    public function test_zero_percent_is_computable_and_not_treated_as_missing(): void
    {
        $owner = $this->seller();
        $listing = Listing::factory()->create(['user_id' => $owner->id]);
        $listing->financials?->forceFill([
            'declared_total_paid' => 1000000,
            'declared_cancellation_percent' => 0,
            'verified_cancellation_percent' => null,
            'cancellation_percent_verified' => false,
        ])->save();

        $this->getJson('/api/v1/listings/'.$listing->id.'/estimate')
            ->assertOk()
            ->assertJsonPath('data.computable', true)
            ->assertJsonPath('data.cancellation.percent', 0)
            ->assertJsonPath('data.cancellation.penalty', 0);
    }

    public function test_disclaimer_prefers_cms_copy(): void
    {
        ContentBlock::factory()->create([
            'key' => 'exit_estimate',
            'body_ar' => 'نص إخلاء من المحتوى',
            'body_en' => 'CMS disclaimer text',
            'is_active' => true,
        ]);
        $owner = $this->seller();
        $listing = Listing::factory()->create(['user_id' => $owner->id]);

        $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/v1/listings/'.$listing->id.'/estimate')
            ->assertOk()
            ->assertJsonPath('data.disclaimer.source', 'cms')
            ->assertJsonPath('data.disclaimer.text', 'نص إخلاء من المحتوى');

        $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/listings/'.$listing->id.'/estimate')
            ->assertOk()
            ->assertJsonPath('data.disclaimer.source', 'cms')
            ->assertJsonPath('data.disclaimer.text', 'CMS disclaimer text');
    }

    public function test_market_range_uses_published_comparables_in_same_area(): void
    {
        $owner = $this->seller();
        $listing = Listing::factory()->published()->create(['user_id' => $owner->id]);
        $peerA = Listing::factory()->published()->create([
            'area_id' => $listing->area_id,
            'unit_type_id' => $listing->unit_type_id,
        ]);
        $peerB = Listing::factory()->published()->create([
            'area_id' => $listing->area_id,
            'unit_type_id' => $listing->unit_type_id,
        ]);
        $peerA->financials?->forceFill(['declared_asking_price' => 3000000, 'asking_price_verified' => false])->save();
        $peerB->financials?->forceFill(['declared_asking_price' => 3400000, 'asking_price_verified' => false])->save();

        $this->getJson('/api/v1/listings/'.$listing->id.'/estimate')
            ->assertOk()
            ->assertJsonPath('data.market.label', 'estimate')
            ->assertJsonPath('data.market.certified_valuation', false)
            ->assertJsonPath('data.market.min', 3000000)
            ->assertJsonPath('data.market.max', 3400000)
            ->assertJsonPath('data.market.sample_size', 2);
    }

    private function seller(): User
    {
        $user = User::factory()->seller()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
