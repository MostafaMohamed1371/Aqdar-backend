<?php

namespace Tests\Integration;

use App\Core\Enums\DeliveryStatus;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Services\ListingModerationService;
use App\Modules\Listing\Services\ListingService;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Setting\Services\SettingService;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\Concerns\UploadsListingDocuments;
use Tests\TestCase;

class NotificationPhase15Test extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;
    use UploadsListingDocuments;

    public function test_hold_notifies_seller_without_phone_and_skips_staff_push(): void
    {
        $seller = User::factory()->seller()->create([
            'name' => 'Hold Seller',
            'phone' => '+201033334444',
        ]);
        $seller->devices()->create([
            'device_uuid' => 'ios-hold',
            'platform' => 'ios',
            'push_token' => 'fcm-hold',
        ]);
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Published,
        ]);
        $this->actingAsStaff(StaffRole::Operations);

        $this->post('/dashboard/listings/'.$listing->id.'/hold', ['reason' => 'شكوى مؤقتة'])->assertRedirect();

        $row = InboxNotification::query()->where('event_key', 'listing_held')->first();
        $this->assertNotNull($row);
        $this->assertSame($seller->id, (int) $row->notifiable_id);
        $this->assertSame($seller->getMorphClass(), $row->notifiable_type);
        $blob = $row->title_ar.$row->body_ar.$row->body_en.json_encode($row->data);
        $this->assertStringContainsString($listing->public_code, $blob);
        $this->assertStringNotContainsString('201033334444', $blob);
        $this->assertStringNotContainsString('Hold Seller', $blob);

        $push = NotificationDelivery::query()
            ->where('notification_id', $row->id)
            ->where('channel', 'push')
            ->first();
        $this->assertNotNull($push);
        $this->assertSame(DeliveryStatus::Sent, $push->status);
        $this->assertStringNotContainsString('201033334444', (string) $push->provider_response);

        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'listing_held')
                ->where('notifiable_type', (new StaffUser)->getMorphClass())
                ->exists()
        );

        Sanctum::actingAs($seller);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.event', 'listing_held')
            ->assertJsonMissing(['phone' => '+201033334444']);
    }

    public function test_unpublish_notifies_seller_listing_suspended(): void
    {
        $seller = User::factory()->seller()->create(['phone' => '+201044445555']);
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Published,
        ]);
        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/listings/'.$listing->id.'/unpublish', ['reason' => 'سحب من السوق'])->assertRedirect();

        $row = InboxNotification::query()->where('event_key', 'listing_suspended')->first();
        $this->assertNotNull($row);
        $this->assertSame($seller->id, (int) $row->notifiable_id);
        $this->assertStringNotContainsString('201044445555', $row->body_ar.$row->body_en.json_encode($row->data));
    }

    public function test_archive_notifies_seller_listing_suspended(): void
    {
        $seller = User::factory()->seller()->create(['phone' => '+201077778888']);
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Published,
        ]);
        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/listings/'.$listing->id.'/archive', ['reason' => 'أرشفة الإعلان'])->assertRedirect();

        $row = InboxNotification::query()->where('event_key', 'listing_suspended')->first();
        $this->assertNotNull($row);
        $this->assertSame($seller->id, (int) $row->notifiable_id);
        $this->assertStringNotContainsString('201077778888', $row->body_ar.$row->body_en.json_encode($row->data));
    }

    public function test_submit_notifies_reviewer_queue_not_buyer(): void
    {
        $ops = $this->makeStaff(StaffRole::Operations);
        $reviewer = $this->makeStaff(StaffRole::Reviewer);
        $finance = $this->makeStaff(StaffRole::Finance);
        $seller = User::factory()->seller()->create();
        $draft = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Draft,
        ]);
        $this->seedRequiredDocuments($draft);
        app(ListingService::class)->submit($draft->fresh(['financials', 'category']), $seller, true);

        $this->assertTrue(
            InboxNotification::query()->where('event_key', 'listing_submitted')->where('notifiable_id', $seller->id)->exists()
        );
        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'staff_new_submission')
                ->where('notifiable_id', $reviewer->id)
                ->exists()
        );
        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'staff_new_submission')
                ->where('notifiable_id', $ops->id)
                ->exists()
        );
        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'staff_new_submission')
                ->where('notifiable_id', $finance->id)
                ->exists()
        );
        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'staff_new_submission')
                ->where('notifiable_id', $seller->id)
                ->exists()
        );

        Sanctum::actingAs($seller);
        $inbox = $this->getJson('/api/v1/notifications')->assertOk();
        $events = collect($inbox->json('data'))->pluck('event')->all();
        $this->assertContains('listing_submitted', $events);
        $this->assertNotContains('staff_new_submission', $events);
        $this->assertNotContains('staff_unassigned_lead', $events);
        $this->assertNotContains('staff_new_buyer_request', $events);

        $staffRow = InboxNotification::query()->where('event_key', 'staff_new_submission')->first();
        $this->assertNotNull($staffRow);
        $this->assertTrue(
            NotificationDelivery::query()
                ->where('notification_id', $staffRow->id)
                ->where('channel', 'push')
                ->where('status', DeliveryStatus::Skipped)
                ->where('skip_reason', 'staff_in_app_only')
                ->exists()
        );
    }

    public function test_assigned_reviewer_gets_submission_instead_of_queue(): void
    {
        $ops = $this->makeStaff(StaffRole::Operations);
        $reviewer = $this->makeStaff(StaffRole::Reviewer);
        $seller = User::factory()->seller()->create();
        $draft = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Draft,
            'assigned_reviewer_id' => $reviewer->id,
        ]);
        $this->seedRequiredDocuments($draft);
        app(ListingService::class)->submit($draft->fresh(['financials', 'category', 'assignedReviewer']), $seller, true);

        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'staff_new_submission')
                ->where('notifiable_id', $reviewer->id)
                ->exists()
        );
        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'staff_new_submission')
                ->where('notifiable_id', $ops->id)
                ->exists()
        );
    }

    public function test_inactive_assigned_reviewer_falls_through_to_queue(): void
    {
        $ops = $this->makeStaff(StaffRole::Operations);
        $inactive = $this->makeStaff(StaffRole::Reviewer, ['is_active' => false]);
        $seller = User::factory()->seller()->create();
        $draft = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Draft,
            'assigned_reviewer_id' => $inactive->id,
        ]);
        $this->seedRequiredDocuments($draft);
        app(ListingService::class)->submit($draft->fresh(['financials', 'category', 'assignedReviewer']), $seller, true);

        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'staff_new_submission')
                ->where('notifiable_id', $inactive->id)
                ->exists()
        );
        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'staff_new_submission')
                ->where('notifiable_id', $ops->id)
                ->exists()
        );
    }

    public function test_resubmit_creates_a_new_unread_staff_submission(): void
    {
        $reviewer = $this->makeStaff(StaffRole::Reviewer);
        $seller = User::factory()->seller()->create();
        $draft = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Draft,
            'assigned_reviewer_id' => $reviewer->id,
        ]);
        $this->seedRequiredDocuments($draft);
        $listing = app(ListingService::class)->submit($draft->fresh(['financials', 'category', 'assignedReviewer']), $seller, true);

        $first = InboxNotification::query()
            ->where('event_key', 'staff_new_submission')
            ->where('notifiable_id', $reviewer->id)
            ->first();
        $this->assertNotNull($first);
        $first->forceFill(['read_at' => now()])->save();

        app(ListingModerationService::class)->requestChanges($listing->fresh(), $reviewer, 'نقص مستند');
        app(ListingService::class)->resubmit($listing->fresh(['financials', 'category', 'assignedReviewer']), $seller);

        $rows = InboxNotification::query()
            ->where('event_key', 'staff_new_submission')
            ->where('notifiable_id', $reviewer->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $rows);
        $this->assertNotNull($rows[0]->read_at);
        $this->assertNull($rows[1]->read_at);
        $this->assertNotSame($rows[0]->idempotency_key, $rows[1]->idempotency_key);
    }

    public function test_unassigned_lead_notifies_ops_not_sales_or_parties(): void
    {
        $ops = $this->makeStaff(StaffRole::Operations);
        $sales = $this->makeStaff(StaffRole::Sales);
        $listing = Listing::factory()->published()->create();
        $buyer = User::factory()->create([
            'name' => 'LeadBuyerSecret',
            'phone' => '+201055556666',
            'is_buyer' => true,
        ]);
        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();

        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'staff_unassigned_lead')
                ->where('notifiable_id', $ops->id)
                ->exists()
        );
        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'staff_unassigned_lead')
                ->where('notifiable_id', $sales->id)
                ->exists()
        );
        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'staff_unassigned_lead')
                ->where('notifiable_id', $buyer->id)
                ->exists()
        );
        $blob = InboxNotification::query()
            ->where('event_key', 'staff_unassigned_lead')
            ->get()
            ->map(fn ($row) => $row->title_ar.$row->body_ar.$row->body_en.json_encode($row->data))
            ->implode(' ');
        $this->assertStringNotContainsString('LeadBuyerSecret', $blob);
        $this->assertStringNotContainsString('201055556666', $blob);
    }

    public function test_assigned_inquiry_does_not_fire_unassigned_lead(): void
    {
        $ops = $this->makeStaff(StaffRole::Operations);
        $sales = $this->makeStaff(StaffRole::Sales);
        app(SettingService::class)->put('lead_assignment_rule', 'round_robin', 'crm');
        $listing = Listing::factory()->published()->create();
        $buyer = User::factory()->create(['is_buyer' => true]);
        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();

        $this->assertFalse(
            InboxNotification::query()->where('event_key', 'staff_unassigned_lead')->exists()
        );
        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'inquiry_created')
                ->where('notifiable_id', $sales->id)
                ->exists()
        );
        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'inquiry_created')
                ->where('notifiable_id', $ops->id)
                ->exists()
        );
    }

    public function test_buyer_request_notifies_staff_queue(): void
    {
        $ops = $this->makeStaff(StaffRole::Operations);
        $sales = $this->makeStaff(StaffRole::Sales);
        $finance = $this->makeStaff(StaffRole::Finance);
        $buyer = User::factory()->create(['is_buyer' => true, 'phone' => '+201066667777']);
        Sanctum::actingAs($buyer);
        $created = $this->postJson('/api/v1/buyer-requests', [
            'intent' => 'living',
            'contact_method' => 'phone',
            'consent' => true,
            'budget_max' => 2000000,
        ])->assertCreated();
        $code = $created->json('data.public_code');

        $row = InboxNotification::query()
            ->where('event_key', 'staff_new_buyer_request')
            ->where('notifiable_id', $ops->id)
            ->first();
        $this->assertNotNull($row);
        $this->assertStringContainsString($code, $row->body_en.$row->body_ar);
        $this->assertStringNotContainsString('201066667777', $row->body_ar.$row->body_en.json_encode($row->data));
        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'staff_new_buyer_request')
                ->where('notifiable_id', $sales->id)
                ->exists()
        );
        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'staff_new_buyer_request')
                ->where('notifiable_id', $finance->id)
                ->exists()
        );
        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'staff_new_buyer_request')
                ->where('notifiable_id', $buyer->id)
                ->exists()
        );
    }

    public function test_guest_is_redirected_from_staff_inbox(): void
    {
        $this->get('/dashboard/inbox')->assertRedirect(route('login'));
    }

    public function test_staff_inbox_shows_alert_and_marks_read(): void
    {
        $ops = $this->actingAsStaff(StaffRole::Operations);
        $listing = Listing::factory()->published()->create();
        Sanctum::actingAs(User::factory()->create(['is_buyer' => true]));
        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();

        $this->actingAs($ops);
        $this->get('/dashboard/inbox')
            ->assertOk()
            ->assertSee(__('aqdar.notifications.inbox'), false)
            ->assertSee(__('aqdar.notifications.unread'), false)
            ->assertSee(__('aqdar.notifications.mark_read'), false)
            ->assertSee(__('aqdar.notifications.events.staff_unassigned_lead.title'), false);

        $note = InboxNotification::query()
            ->where('event_key', 'staff_unassigned_lead')
            ->where('notifiable_id', $ops->id)
            ->first();
        $this->assertNotNull($note);
        $this->assertNull($note->read_at);
        $this->post('/dashboard/inbox/'.$note->id.'/read')->assertRedirect();
        $this->assertNotNull($note->fresh()->read_at);

        $this->get('/dashboard/inbox')
            ->assertOk()
            ->assertDontSee(__('aqdar.notifications.unread'), false)
            ->assertDontSee(__('aqdar.notifications.mark_read'), false);

        $other = $this->makeStaff(StaffRole::Reviewer);
        $this->actingAs($other);
        $this->post('/dashboard/inbox/'.$note->id.'/read')->assertNotFound();
    }
}
