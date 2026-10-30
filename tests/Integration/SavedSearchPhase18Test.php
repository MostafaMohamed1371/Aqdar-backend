<?php

namespace Tests\Integration;

use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Listing\Jobs\MatchSavedSearchesJob;
use App\Modules\Listing\Models\Favorite;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\SavedSearch;
use App\Modules\Notification\Database\Seeders\NotificationSeeder;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Setting\Services\SettingService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\Concerns\UploadsListingDocuments;
use Tests\TestCase;

class SavedSearchPhase18Test extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;
    use UploadsListingDocuments;

    public function test_guest_cannot_manage_saved_searches(): void
    {
        $this->getJson('/api/v1/saved-searches')->assertUnauthorized();
        $this->postJson('/api/v1/saved-searches', ['filters' => ['segment' => 'distressed']])->assertUnauthorized();
        $this->patchJson('/api/v1/saved-searches/1', ['notify' => false])->assertUnauthorized();
    }

    public function test_user_can_create_list_and_delete_saved_searches_when_smart_is_off(): void
    {
        $this->seed(NotificationSeeder::class);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/saved-searches', [
            'name' => 'القاهرة الجديدة',
            'notify' => true,
            'filters' => ['area_id' => 1, 'price_max' => 3000000],
        ])->assertCreated()->json('data');

        $this->assertSame('القاهرة الجديدة', $created['name']);
        $this->assertTrue($created['notify']);
        $this->assertSame(1, $created['filters']['area_id']);
        $this->assertArrayNotHasKey('sort', $created['filters']);

        $this->getJson('/api/v1/saved-searches')
            ->assertOk()
            ->assertJsonPath('data.0.id', $created['id']);

        $this->deleteJson('/api/v1/saved-searches/'.$created['id'])
            ->assertOk();
        $this->assertDatabaseMissing('saved_searches', ['id' => $created['id']]);
    }

    public function test_saved_search_cap_is_twenty(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->count(20)->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/saved-searches', ['filters' => ['segment' => 'distressed']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filters');
    }

    public function test_user_cannot_delete_someone_elses_saved_search(): void
    {
        $owner = User::factory()->create();
        $other = SavedSearch::factory()->create(['user_id' => $owner->id]);
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/saved-searches/'.$other->id)->assertNotFound();
        $this->patchJson('/api/v1/saved-searches/'.$other->id, ['notify' => false])->assertNotFound();
        $this->assertDatabaseHas('saved_searches', ['id' => $other->id]);
    }

    public function test_user_can_patch_saved_search_name_notify_and_filters(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $row = SavedSearch::factory()->create([
            'user_id' => $user->id,
            'name' => 'قديم',
            'notify' => true,
            'filters' => ['area_id' => 1],
        ]);

        $this->patchJson('/api/v1/saved-searches/'.$row->id, [
            'name' => 'القاهرة الجديدة',
            'notify' => false,
            'filters' => ['area_id' => 2, 'price_max' => 2500000],
        ])->assertOk()
            ->assertJsonPath('data.name', 'القاهرة الجديدة')
            ->assertJsonPath('data.notify', false)
            ->assertJsonPath('data.filters.area_id', 2)
            ->assertJsonPath('data.filters.price_max', 2500000);
    }

    public function test_publish_notifies_matching_saved_search_when_smart_is_on(): void
    {
        $this->seed(NotificationSeeder::class);
        app(SettingService::class)->put('feature_smart_notifications', '1', 'notifications');

        $buyer = User::factory()->create(['name' => 'Buyer Secret', 'phone' => '+201099988877']);
        $seller = User::factory()->seller()->create(['name' => 'Seller Secret', 'phone' => '+201011111111']);
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Approved,
        ]);
        $this->seedApprovedRequiredDocuments($listing);
        SavedSearch::factory()->create([
            'user_id' => $buyer->id,
            'filters' => ['area_id' => $listing->area_id],
            'notify' => true,
        ]);

        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'جاهز للسوق'])->assertRedirect();

        $row = InboxNotification::query()
            ->where('event_key', 'smart_saved_search')
            ->where('notifiable_id', $buyer->id)
            ->first();
        $this->assertNotNull($row);
        $this->assertStringContainsString($listing->public_code, $row->body_en);
        $payload = json_encode($row->data).$row->body_ar.$row->body_en.$row->title_ar.$row->title_en;
        $this->assertStringNotContainsString('Seller Secret', $payload);
        $this->assertStringNotContainsString('201011111111', $payload);
        $this->assertStringNotContainsString('Buyer Secret', $payload);

        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/notifications?category=smart')
            ->assertOk()
            ->assertJsonPath('data.0.event', 'smart_saved_search')
            ->assertJsonPath('data.0.category', 'smart');
        $inbox = $this->getJson('/api/v1/notifications?category=smart')->json('data.0.context');
        $this->assertArrayNotHasKey('phone', $inbox ?? []);
        $encoded = json_encode($inbox);
        $this->assertStringNotContainsString('Seller Secret', $encoded);
        $this->assertStringNotContainsString('201011111111', $encoded);

        MatchSavedSearchesJob::dispatchSync($listing->id);
        $this->assertSame(1, InboxNotification::query()->where('event_key', 'smart_saved_search')->where('notifiable_id', $buyer->id)->count());
    }

    public function test_publish_does_not_alert_when_smart_flag_is_off(): void
    {
        $this->seed(NotificationSeeder::class);
        $buyer = User::factory()->create();
        $listing = Listing::factory()->create(['status' => ListingStatus::Approved]);
        $this->seedApprovedRequiredDocuments($listing);
        SavedSearch::factory()->create([
            'user_id' => $buyer->id,
            'filters' => ['area_id' => $listing->area_id],
            'notify' => true,
        ]);

        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'جاهز للسوق'])->assertRedirect();

        $this->assertFalse(
            InboxNotification::query()->where('event_key', 'smart_saved_search')->exists()
        );
    }

    public function test_owner_is_not_notified_about_their_own_listing(): void
    {
        $this->seed(NotificationSeeder::class);
        app(SettingService::class)->put('feature_smart_notifications', '1', 'notifications');
        $seller = User::factory()->seller()->create();
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Approved,
        ]);
        $this->seedApprovedRequiredDocuments($listing);
        SavedSearch::factory()->create([
            'user_id' => $seller->id,
            'filters' => ['area_id' => $listing->area_id],
            'notify' => true,
        ]);

        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'جاهز للسوق'])->assertRedirect();

        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'smart_saved_search')
                ->where('notifiable_id', $seller->id)
                ->exists()
        );
    }

    public function test_notify_false_skips_match_alerts(): void
    {
        $this->seed(NotificationSeeder::class);
        app(SettingService::class)->put('feature_smart_notifications', '1', 'notifications');
        $buyer = User::factory()->create();
        $listing = Listing::factory()->create(['status' => ListingStatus::Approved]);
        $this->seedApprovedRequiredDocuments($listing);
        SavedSearch::factory()->create([
            'user_id' => $buyer->id,
            'filters' => ['area_id' => $listing->area_id],
            'notify' => false,
        ]);

        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'جاهز للسوق'])->assertRedirect();

        $this->assertFalse(
            InboxNotification::query()->where('event_key', 'smart_saved_search')->exists()
        );
    }

    public function test_verified_asking_price_drop_alerts_matching_search_without_pii(): void
    {
        $this->seed(NotificationSeeder::class);
        app(SettingService::class)->put('feature_smart_notifications', '1', 'notifications');

        $buyer = User::factory()->create(['name' => 'Buyer Secret', 'phone' => '+201099988877']);
        $seller = User::factory()->seller()->create(['name' => 'Seller Secret', 'phone' => '+201011111111']);
        $listing = Listing::factory()->published()->create(['user_id' => $seller->id]);
        SavedSearch::factory()->create([
            'user_id' => $buyer->id,
            'filters' => ['area_id' => $listing->area_id],
            'notify' => true,
        ]);

        $this->actingAsStaff(StaffRole::Reviewer);
        $this->post('/dashboard/listings/'.$listing->id.'/verify-financials', [
            'reason' => 'Matched developer statement',
            'verified_asking_price' => 1500000,
        ])->assertRedirect();

        $row = InboxNotification::query()
            ->where('event_key', 'smart_price_drop')
            ->where('notifiable_id', $buyer->id)
            ->first();
        $this->assertNotNull($row);
        $this->assertStringContainsString('1500000', $row->body_en);
        $payload = json_encode($row->data).$row->body_ar.$row->body_en.$row->title_ar.$row->title_en;
        $this->assertStringNotContainsString('Seller Secret', $payload);
        $this->assertStringNotContainsString('201011111111', $payload);
        $this->assertStringNotContainsString('Buyer Secret', $payload);

        MatchSavedSearchesJob::dispatchSync($listing->id, 'price_drop');
        $this->assertSame(1, InboxNotification::query()->where('event_key', 'smart_price_drop')->where('notifiable_id', $buyer->id)->count());
    }

    public function test_verified_price_drop_alerts_buyers_who_saved_the_listing(): void
    {
        $this->seed(NotificationSeeder::class);
        app(SettingService::class)->put('feature_smart_notifications', '1', 'notifications');

        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create(['name' => 'Seller Secret']);
        $listing = Listing::factory()->published()->create(['user_id' => $seller->id]);
        Favorite::query()->create(['user_id' => $buyer->id, 'listing_id' => $listing->id]);

        $this->actingAsStaff(StaffRole::Reviewer);
        $this->post('/dashboard/listings/'.$listing->id.'/verify-financials', [
            'reason' => 'Matched developer statement',
            'verified_asking_price' => 1500000,
        ])->assertRedirect();
        MatchSavedSearchesJob::dispatchSync($listing->id, 'price_drop');

        $rows = InboxNotification::query()
            ->where('event_key', 'smart_favorite_updated')
            ->where('notifiable_id', $buyer->id)
            ->get();
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('1500000', $rows->first()->body_en);
        $this->assertStringNotContainsString('Seller Secret', json_encode($rows->first()->data).$rows->first()->body_en);
    }

    public function test_favorite_price_alert_stays_off_without_smart_flag(): void
    {
        app(SettingService::class)->put('feature_smart_notifications', '0', 'notifications');
        $buyer = User::factory()->create();
        $listing = Listing::factory()->published()->create();
        Favorite::query()->create(['user_id' => $buyer->id, 'listing_id' => $listing->id]);

        $this->actingAsStaff(StaffRole::Reviewer);
        $this->post('/dashboard/listings/'.$listing->id.'/verify-financials', [
            'reason' => 'Matched developer statement',
            'verified_asking_price' => 1500000,
        ])->assertRedirect();

        $this->assertSame(0, InboxNotification::query()->where('event_key', 'smart_favorite_updated')->count());
    }
}
