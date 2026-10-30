<?php

namespace Tests\Integration;

use App\Core\Enums\Permission;
use App\Core\Enums\StaffRole;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Notification\Models\NotificationBroadcast;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Staff\Services\StaffService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class BroadcastPhase18Test extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_operations_broadcast_stays_pending_until_another_staff_approves(): void
    {
        $optedIn = User::factory()->create();
        $optedIn->notificationPreference()->create([
            'push_enabled' => true,
            'marketing_opt_in' => true,
        ]);

        $ops = $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/notifications/broadcasts', [
            'title_ar' => 'عرض حصري هذا الأسبوع',
            'title_en' => 'Exclusive offer this week',
            'body_ar' => 'انضم للعروض العامة بدون بيانات تواصل.',
            'body_en' => 'Join public offers with no contact details.',
            'audience' => 'marketing_opted_in',
        ])->assertRedirect();

        $broadcast = NotificationBroadcast::query()->latest('id')->first();
        $this->assertNotNull($broadcast);
        $this->assertSame('pending', $broadcast->status);
        $this->assertNull($broadcast->approved_at);
        $this->assertFalse(
            InboxNotification::query()->where('event_key', 'marketing_broadcast')->exists()
        );

        $this->from('/dashboard/content?tab=schedule')
            ->post('/dashboard/notifications/broadcasts/'.$broadcast->id.'/approve')
            ->assertRedirect()
            ->assertSessionHasErrors();
        $this->assertNull($broadcast->fresh()->approved_at);

        $this->actingAsStaff(StaffRole::SuperAdmin);
        $this->post('/dashboard/notifications/broadcasts/'.$broadcast->id.'/approve')->assertRedirect();

        $this->assertNotNull($broadcast->fresh()->approved_at);
        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'marketing_broadcast')
                ->where('notifiable_id', $optedIn->id)
                ->where('title_ar', 'عرض حصري هذا الأسبوع')
                ->exists()
        );
        $this->assertSame($ops->id, (int) $broadcast->fresh()->created_by);
    }

    public function test_super_admin_broadcast_still_sends_immediately(): void
    {
        $optedIn = User::factory()->create();
        $optedIn->notificationPreference()->create([
            'push_enabled' => true,
            'marketing_opt_in' => true,
        ]);

        $this->actingAsStaff(StaffRole::SuperAdmin);
        $this->post('/dashboard/notifications/broadcasts', [
            'title_ar' => 'عرض حصري هذا الأسبوع',
            'title_en' => 'Exclusive offer this week',
            'body_ar' => 'انضم للعروض العامة بدون بيانات تواصل.',
            'body_en' => 'Join public offers with no contact details.',
            'audience' => 'marketing_opted_in',
        ])->assertRedirect();

        $broadcast = NotificationBroadcast::query()->latest('id')->first();
        $this->assertNotNull($broadcast?->approved_at);
        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'marketing_broadcast')
                ->where('notifiable_id', $optedIn->id)
                ->exists()
        );
    }

    public function test_due_dispatcher_skips_unapproved_broadcasts(): void
    {
        $broadcast = NotificationBroadcast::query()->create([
            'title_ar' => 'مسودة',
            'title_en' => 'Draft',
            'body_ar' => 'بدون اعتماد',
            'body_en' => 'Not approved',
            'audience' => 'marketing_opted_in',
            'status' => 'pending',
            'scheduled_at' => now()->subMinute(),
        ]);

        $count = app(NotificationService::class)->dispatchDueBroadcasts();

        $this->assertSame(0, $count);
        $this->assertNull($broadcast->fresh()->sent_at);
    }

    public function test_operations_can_open_schedule_and_reviewer_cannot(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $this->get('/dashboard/content?tab=schedule')
            ->assertOk()
            ->assertSee('ADM-11', false)
            ->assertDontSee(route('dashboard.notifications.test-send'), false);

        $this->actingAsStaff(StaffRole::Reviewer);
        $this->get('/dashboard/content')->assertForbidden();
        $this->post('/dashboard/notifications/broadcasts', [
            'title_ar' => 'تجربة',
            'body_ar' => 'نص',
        ])->assertForbidden();
    }

    public function test_operations_cannot_edit_catalog_from_content(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/content', [
            'type' => 'banner',
            'key' => 'ops-banner',
            'title_ar' => 'بنر',
        ])->assertForbidden();
    }

    public function test_approved_future_broadcast_sends_when_due(): void
    {
        $optedIn = User::factory()->create();
        $optedIn->notificationPreference()->create([
            'push_enabled' => true,
            'marketing_opt_in' => true,
        ]);

        $this->actingAsStaff(StaffRole::Operations);
        $this->post('/dashboard/notifications/broadcasts', [
            'title_ar' => 'عرض لاحق',
            'title_en' => 'Later offer',
            'body_ar' => 'انضم للعروض العامة بدون بيانات تواصل.',
            'body_en' => 'Join public offers with no contact details.',
            'audience' => 'marketing_opted_in',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect();

        $broadcast = NotificationBroadcast::query()->latest('id')->first();
        $this->assertSame('pending', $broadcast?->status);
        $this->assertFalse(InboxNotification::query()->where('event_key', 'marketing_broadcast')->exists());

        $this->actingAsStaff(StaffRole::SuperAdmin);
        $this->post('/dashboard/notifications/broadcasts/'.$broadcast->id.'/approve')->assertRedirect();

        $approved = $broadcast->fresh();
        $this->assertNotNull($approved?->approved_at);
        $this->assertSame('scheduled', $approved->status);
        $this->assertNull($approved->sent_at);
        $this->assertSame(0, app(NotificationService::class)->dispatchDueBroadcasts());
        $this->assertFalse(InboxNotification::query()->where('event_key', 'marketing_broadcast')->exists());

        $this->travel(2)->days();
        $this->assertGreaterThanOrEqual(1, app(NotificationService::class)->dispatchDueBroadcasts());
        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'marketing_broadcast')
                ->where('notifiable_id', $optedIn->id)
                ->exists()
        );
        $this->assertNotNull($broadcast->fresh()->sent_at);
    }

    public function test_adm06_matrix_includes_approve_broadcasts_for_operations(): void
    {
        $this->actingAsStaff(StaffRole::SuperAdmin);
        $matrix = app(StaffService::class)->permissionMatrix();

        $this->assertTrue($matrix[Permission::ApproveBroadcasts->value][StaffRole::Operations->value]);
        $this->assertTrue($matrix[Permission::ApproveBroadcasts->value][StaffRole::SuperAdmin->value]);
        $this->assertFalse($matrix[Permission::ApproveBroadcasts->value][StaffRole::Reviewer->value]);
        $this->assertFalse($matrix[Permission::ApproveBroadcasts->value][StaffRole::Sales->value]);
        $this->assertFalse($matrix[Permission::ApproveBroadcasts->value][StaffRole::Finance->value]);

        $this->get('/dashboard/staff')
            ->assertOk()
            ->assertSee(Permission::ApproveBroadcasts->labelAr(), false);
    }
}
