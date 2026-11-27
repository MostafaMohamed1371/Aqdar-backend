<?php

namespace Tests\Feature;

use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Listing\Models\Listing;
use App\Modules\Notification\Models\InboxNotification;
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

        $live = $this->getJson(route('dashboard.live'))
            ->assertOk()
            ->assertJsonPath('data.needs.listings_in_review', 2)
            ->assertJsonPath('data.cursors.listings', $newer->id)
            ->assertJsonStructure([
                'data' => [
                    'listings',
                    'leads',
                    'requests',
                    'cases',
                    'documents',
                    'media',
                    'badges' => ['ADM-02', 'ADM-03'],
                    'needs' => ['listings_in_review', 'media_pending'],
                    'cursors' => ['listings', 'media', 'inbox'],
                    'inbox' => ['unread_count', 'items'],
                    'polled_at',
                ],
            ]);

        $ids = collect($live->json('data.listings'))->pluck('id')->all();
        $this->assertContains($older->id, $ids);
        $this->assertContains($newer->id, $ids);

        $this->get(route('dashboard.listings.index'))
            ->assertOk()
            ->assertSee('data-live-table="listings"', false)
            ->assertSee('data-desk-live="/dashboard/live"', false);
    }

    public function test_desk_live_returns_staff_inbox_items_for_bell_without_refresh(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Operations);

        $older = InboxNotification::factory()->create([
            'notifiable_type' => $staff->getMorphClass(),
            'notifiable_id' => $staff->id,
            'event_key' => 'staff_new_submission',
            'title_en' => 'Older listing',
            'body_en' => 'Older body',
            'read_at' => null,
        ]);
        $newer = InboxNotification::factory()->create([
            'notifiable_type' => $staff->getMorphClass(),
            'notifiable_id' => $staff->id,
            'event_key' => 'staff_new_submission',
            'title_en' => 'Listing awaiting review',
            'body_en' => 'AQ-100 awaiting review.',
            'read_at' => null,
        ]);

        $this->getJson(route('dashboard.live', ['inbox_after' => $older->id]))
            ->assertOk()
            ->assertJsonPath('data.inbox.unread_count', 2)
            ->assertJsonPath('data.inbox.items.0.id', $newer->id)
            ->assertJsonPath('data.inbox.items.0.title', 'Listing awaiting review');
    }

    public function test_guest_cannot_poll_desk_live(): void
    {
        $this->getJson(route('dashboard.live'))->assertUnauthorized();
    }
}
