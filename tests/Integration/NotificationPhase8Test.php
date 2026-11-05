<?php

namespace Tests\Integration;

use App\Core\Enums\BuyerRequestStatus;
use App\Core\Enums\CaseStage;
use App\Core\Enums\CaseStatus;
use App\Core\Enums\DeliveryStatus;
use App\Core\Enums\LeadStatus;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Services\ListingService;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Services\Push\PushGateway;
use App\Modules\Setting\Models\Setting;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\Concerns\UploadsListingDocuments;
use Tests\TestCase;

class NotificationPhase8Test extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;
    use UploadsListingDocuments;

    public function test_guest_cannot_read_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/me/notification-preferences')->assertUnauthorized();
        $this->patchJson('/api/v1/me/notification-preferences', ['push_enabled' => false])->assertUnauthorized();
    }

    public function test_uat14_listing_publish_persists_inbox_and_queues_safe_push(): void
    {
        $seller = User::factory()->seller()->create([
            'name' => 'Seller Secret',
            'phone' => '+201011111111',
        ]);
        $seller->devices()->create([
            'device_uuid' => 'ios-1',
            'platform' => 'ios',
            'push_token' => 'fcm-token-ok',
        ]);
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Approved,
        ]);
        $this->seedApprovedRequiredDocuments($listing);
        $this->actingAsStaff(StaffRole::Operations);

        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'جاهز للسوق'])->assertRedirect();

        $row = InboxNotification::query()->where('event_key', 'listing_published')->first();
        $this->assertNotNull($row);
        $this->assertSame($seller->id, (int) $row->notifiable_id);
        $this->assertStringContainsString($listing->public_code, $row->body_en);
        $this->assertStringNotContainsString('Seller Secret', $row->body_ar.$row->body_en);
        $this->assertStringNotContainsString('201011111111', json_encode($row->data).$row->body_en);

        $push = NotificationDelivery::query()
            ->where('notification_id', $row->id)
            ->where('channel', 'push')
            ->first();
        $this->assertNotNull($push);
        $this->assertSame(DeliveryStatus::Sent, $push->status);
        $this->assertStringNotContainsString('201011111111', (string) $push->provider_response);

        Sanctum::actingAs($seller);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.event', 'listing_published')
            ->assertJsonMissing(['phone' => '+201011111111']);
    }

    public function test_uat18_push_disabled_still_keeps_in_app_readable(): void
    {
        $seller = User::factory()->seller()->create();
        NotificationPreference::query()->create([
            'user_id' => $seller->id,
            'push_enabled' => false,
            'marketing_opt_in' => false,
        ]);
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Approved,
        ]);
        $this->seedApprovedRequiredDocuments($listing);
        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'نشر'])->assertRedirect();

        Sanctum::actingAs($seller);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.event', 'listing_published');
        $this->assertDatabaseHas('notification_deliveries', [
            'channel' => 'push',
            'status' => 'skipped',
            'skip_reason' => 'push_disabled',
        ]);
    }

    public function test_inquiry_notifies_buyer_and_seller_without_buyer_pii(): void
    {
        $listing = Listing::factory()->published()->create();
        $seller = $listing->owner;
        $buyer = User::factory()->create([
            'is_buyer' => true,
            'name' => 'Buyer Hidden',
            'phone' => '+201022222222',
        ]);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/inquiries', [
            'listing_id' => $listing->id,
            'message' => 'مهتم بالمعاينة',
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();

        $sellerNote = InboxNotification::query()
            ->where('event_key', 'seller_interest')
            ->where('notifiable_id', $seller->id)
            ->first();
        $this->assertNotNull($sellerNote);
        $blob = $sellerNote->title_ar.$sellerNote->body_ar.$sellerNote->body_en.json_encode($sellerNote->data);
        $this->assertStringNotContainsString('Buyer Hidden', $blob);
        $this->assertStringNotContainsString('201022222222', $blob);

        $this->assertTrue(
            InboxNotification::query()->where('event_key', 'inquiry_created')->where('notifiable_id', $buyer->id)->exists()
        );
    }

    public function test_case_stage_uses_client_label_not_internal_stage(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $case = TransferCase::factory()->create(['status' => CaseStatus::Open]);
        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/stage', [
                'stage' => CaseStage::QualifyBuyer->value,
                'reason' => 'تم التأهيل',
            ])->assertRedirect();

        $row = InboxNotification::query()->where('event_key', 'case_stage_changed')->first();
        $this->assertNotNull($row);
        $this->assertStringNotContainsString('qualify_buyer', strtolower($row->body_en.$row->body_ar));
        $this->assertStringContainsString(__('aqdar.cases.client.buyer_qualified'), $row->body_en.' '.$row->body_ar);
    }

    public function test_read_and_read_all_and_preferences(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $note = InboxNotification::factory()->create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
        ]);

        $this->postJson('/api/v1/notifications/'.$note->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.read_at', fn ($value) => $value !== null);
        $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.unread_count', 0);

        $this->patchJson('/api/v1/me/notification-preferences', [
            'push_enabled' => false,
            'marketing_opt_in' => true,
        ])->assertOk()
            ->assertJsonPath('data.push_enabled', false)
            ->assertJsonPath('data.marketing_opt_in', true)
            ->assertJsonPath('data.in_app_always_on', true);
    }

    public function test_smart_category_is_empty_when_flag_is_off(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        InboxNotification::factory()->create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'event_key' => 'smart_new_match',
            'category' => 'smart',
        ]);

        $this->getJson('/api/v1/notifications?category=smart')
            ->assertOk()
            ->assertJsonPath('meta.smart_enabled', false)
            ->assertJsonCount(0, 'data');
    }

    public function test_expired_push_token_is_cleared(): void
    {
        $seller = User::factory()->seller()->create();
        $device = $seller->devices()->create([
            'device_uuid' => 'ios-expired',
            'platform' => 'ios',
            'push_token' => 'token-expired',
        ]);
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Approved,
        ]);
        $this->seedApprovedRequiredDocuments($listing);
        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'نشر'])->assertRedirect();
        $this->assertNull($device->fresh()->push_token);
        $this->assertDatabaseHas('notification_deliveries', [
            'skip_reason' => 'token_expired',
            'status' => 'failed',
        ]);
    }

    public function test_duplicate_event_does_not_create_a_second_row(): void
    {
        $seller = User::factory()->seller()->create();
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Approved,
        ]);
        $this->seedApprovedRequiredDocuments($listing);
        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'نشر'])->assertRedirect();
        $listing->forceFill(['status' => ListingStatus::Approved])->save();
        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'نشر مرة أخرى'])->assertRedirect();

        $this->assertSame(1, InboxNotification::query()->where('event_key', 'listing_published')->count());
    }

    public function test_guest_preferences_get_is_unauthorized_and_owner_can_read_defaults(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.push_enabled', true)
            ->assertJsonPath('data.marketing_opt_in', false)
            ->assertJsonPath('data.in_app_always_on', true);

        $this->getJson('/api/v1/me/preferences')
            ->assertOk()
            ->assertJsonPath('data.push_enabled', true)
            ->assertJsonPath('data.marketing_opt_in', false)
            ->assertJsonPath('data.in_app_always_on', true);
    }

    public function test_quiet_hours_skip_push(): void
    {
        $this->travelTo('2026-10-29 12:00:00');
        Setting::query()->updateOrCreate(
            ['key' => 'notification_quiet_hours'],
            ['value' => '00:00-23:59', 'group' => 'notifications'],
        );
        $seller = User::factory()->seller()->create();
        $seller->devices()->create([
            'device_uuid' => 'ios-quiet',
            'platform' => 'ios',
            'push_token' => 'fcm-token-quiet',
        ]);
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Approved,
        ]);
        $this->seedApprovedRequiredDocuments($listing);
        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'نشر'])->assertRedirect();

        $this->assertDatabaseHas('notification_deliveries', [
            'channel' => 'push',
            'status' => 'skipped',
            'skip_reason' => 'quiet_hours',
        ]);
        Sanctum::actingAs($seller);
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.0.event', 'listing_published');
    }

    public function test_listing_submitted_needs_changes_and_rejected_notify_owner(): void
    {
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

        $inReview = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::InReview,
        ]);
        $this->actingAsStaff(StaffRole::Reviewer);
        $this->post('/dashboard/listings/'.$inReview->id.'/request-changes', ['reason' => 'الصور غير واضحة'])->assertRedirect();
        $this->assertTrue(
            InboxNotification::query()->where('event_key', 'listing_needs_changes')->where('notifiable_id', $seller->id)->exists()
        );

        $toReject = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::InReview,
        ]);
        $this->post('/dashboard/listings/'.$toReject->id.'/reject', ['reason' => 'مستندات غير مكتملة'])->assertRedirect();
        $this->assertTrue(
            InboxNotification::query()->where('event_key', 'listing_rejected')->where('notifiable_id', $seller->id)->exists()
        );
    }

    public function test_buyer_request_created_and_updated_notify_buyer(): void
    {
        $buyer = User::factory()->create(['is_buyer' => true]);
        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/buyer-requests', [
            'intent' => 'living',
            'contact_method' => 'phone',
            'consent' => true,
            'budget_max' => 3500000,
        ])->assertCreated();
        $this->assertTrue(
            InboxNotification::query()->where('event_key', 'buyer_request_created')->where('notifiable_id', $buyer->id)->exists()
        );

        $request = BuyerRequest::factory()->create(['user_id' => $buyer->id]);
        $this->actingAsStaff(StaffRole::Operations);
        $this->from('/dashboard/requests/'.$request->id)
            ->post('/dashboard/requests/'.$request->id.'/status', [
                'status' => BuyerRequestStatus::Matching->value,
                'reason' => 'بدء المطابقة',
            ])->assertRedirect();
        $this->assertTrue(
            InboxNotification::query()->where('event_key', 'buyer_request_updated')->where('notifiable_id', $buyer->id)->exists()
        );
    }

    public function test_overdue_followup_notifies_assigned_sales(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Sales);
        Lead::factory()->create([
            'assigned_to' => $staff->id,
            'status' => LeadStatus::New,
            'sla_due_at' => now()->subHour(),
        ]);

        $this->artisan('aqdar:notify-overdue-followups')->assertSuccessful();

        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'sales_followup_overdue')
                ->where('notifiable_type', $staff->getMorphClass())
                ->where('notifiable_id', $staff->id)
                ->exists()
        );
        $this->assertDatabaseHas('notification_deliveries', [
            'channel' => 'push',
            'skip_reason' => 'staff_in_app_only',
        ]);
    }

    public function test_missed_scheduled_followup_alerts_assignee_and_managers_until_logged(): void
    {
        $sales = $this->actingAsStaff(StaffRole::Sales);
        $manager = $this->actingAsStaff(StaffRole::Operations);
        $lead = Lead::factory()->create(['assigned_to' => $sales->id, 'status' => LeadStatus::Contacted]);
        $this->travel(-3)->days();
        $lead->activities()->create([
            'actor_id' => $sales->id,
            'type' => 'call',
            'body' => 'Call back on Sunday',
            'due_at' => now()->addDay(),
        ]);
        $this->travelBack();

        $this->assertSame(1, Lead::query()->overdueFollowUp()->count());
        $this->artisan('aqdar:notify-overdue-followups')->assertSuccessful();

        foreach ([$sales, $manager] as $staff) {
            $this->assertTrue(
                InboxNotification::query()
                    ->where('event_key', 'sales_followup_overdue')
                    ->where('notifiable_type', $staff->getMorphClass())
                    ->where('notifiable_id', $staff->id)
                    ->exists()
            );
        }

        $lead->activities()->create(['actor_id' => $sales->id, 'type' => 'call', 'body' => 'Called back']);
        $this->assertSame(0, Lead::query()->overdueFollowUp()->count());
    }

    public function test_other_user_cannot_mark_notification_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $note = InboxNotification::factory()->create([
            'notifiable_type' => $owner->getMorphClass(),
            'notifiable_id' => $owner->id,
        ]);
        Sanctum::actingAs($other);
        $this->postJson('/api/v1/notifications/'.$note->id.'/read')->assertNotFound();
    }

    public function test_unread_and_listing_category_filters(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $unread = InboxNotification::factory()->create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'event_key' => 'listing_published',
            'category' => 'listing',
            'read_at' => null,
        ]);
        InboxNotification::factory()->create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'event_key' => 'inquiry_created',
            'category' => 'inquiry',
            'read_at' => now(),
        ]);

        $this->getJson('/api/v1/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unread->id);

        $this->getJson('/api/v1/notifications?category=listing')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'listing_published');
    }

    public function test_already_sent_push_is_not_retried(): void
    {
        $seller = User::factory()->seller()->create();
        $seller->devices()->create([
            'device_uuid' => 'ios-retry',
            'platform' => 'ios',
            'push_token' => 'fcm-token-retry',
        ]);
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Approved,
        ]);
        $this->seedApprovedRequiredDocuments($listing);
        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'نشر'])->assertRedirect();

        $delivery = NotificationDelivery::query()->where('channel', 'push')->first();
        $this->assertNotNull($delivery);
        $this->assertSame(DeliveryStatus::Sent, $delivery->status);
        $attempts = $delivery->attempts;

        app(NotificationService::class)->attemptPush($delivery->id);

        $this->assertSame($attempts, $delivery->fresh()->attempts);
        $this->assertSame(DeliveryStatus::Sent, $delivery->fresh()->status);
    }

    public function test_broadcast_uses_form_copy_and_skips_users_without_opt_in(): void
    {
        $optedIn = User::factory()->create();
        $optedIn->notificationPreference()->create([
            'push_enabled' => true,
            'marketing_opt_in' => true,
        ]);
        $optedOut = User::factory()->create();
        $optedOut->notificationPreference()->create([
            'push_enabled' => true,
            'marketing_opt_in' => false,
        ]);

        $this->actingAsStaff(StaffRole::SuperAdmin);
        $this->post('/dashboard/notifications/broadcasts', [
            'title_ar' => 'عرض حصري هذا الأسبوع',
            'title_en' => 'Exclusive offer this week',
            'body_ar' => 'انضم للعروض العامة بدون بيانات تواصل.',
            'body_en' => 'Join public offers with no contact details.',
            'audience' => 'marketing_opted_in',
        ])->assertRedirect();

        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'marketing_broadcast')
                ->where('notifiable_id', $optedIn->id)
                ->where('title_ar', 'عرض حصري هذا الأسبوع')
                ->exists()
        );
        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'marketing_broadcast')
                ->where('notifiable_id', $optedOut->id)
                ->exists()
        );
        $this->assertFalse(
            InboxNotification::query()
                ->where('event_key', 'marketing_broadcast')
                ->where('title_ar', 'إعلان المنصة')
                ->exists()
        );
    }

    public function test_fcm_driver_posts_http_v1_without_party_pii(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $pem);
        config([
            'aqdar.notifications.push_driver' => 'fcm',
            'aqdar.notifications.fcm.project_id' => 'aqdar-eg',
            'aqdar.notifications.fcm.client_email' => 'firebase-adminsdk@aqdar-eg.iam.gserviceaccount.com',
            'aqdar.notifications.fcm.private_key' => $pem,
        ]);
        $this->app->forgetInstance(NotificationService::class);
        $this->app->forgetInstance(PushGateway::class);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.integration',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/v1/projects/aqdar-eg/messages:send' => Http::response([
                'name' => 'projects/aqdar-eg/messages/42',
            ]),
        ]);

        $seller = User::factory()->seller()->create([
            'name' => 'Seller Secret',
            'phone' => '+201011111111',
        ]);
        $seller->devices()->create([
            'device_uuid' => 'ios-fcm',
            'platform' => 'ios',
            'push_token' => 'fcm-live-token',
        ]);
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Approved,
        ]);
        $this->seedApprovedRequiredDocuments($listing);
        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'نشر'])->assertRedirect();

        $push = NotificationDelivery::query()->where('channel', 'push')->first();
        $this->assertNotNull($push);
        $this->assertSame(DeliveryStatus::Sent, $push->status);
        $this->assertSame('projects/aqdar-eg/messages/42', $push->provider_response);
        $this->assertStringNotContainsString('201011111111', (string) $push->provider_response);

        Http::assertSent(function ($request) use ($listing): bool {
            if (! str_contains($request->url(), 'messages:send')) {
                return false;
            }
            $encoded = json_encode($request->data());

            return str_contains((string) $encoded, (string) $listing->public_code)
                && ! str_contains((string) $encoded, '201011111111')
                && ! str_contains((string) $encoded, 'Seller Secret');
        });
    }
}
