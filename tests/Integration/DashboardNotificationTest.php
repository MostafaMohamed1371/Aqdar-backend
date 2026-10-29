<?php

namespace Tests\Integration;

use App\Core\Enums\StaffRole;
use App\Modules\Category\Models\Category;
use App\Modules\Notification\Database\Seeders\NotificationSeeder;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Notification\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardNotificationTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_super_admin_sees_templates_broadcast_and_log(): void
    {
        $this->seed(NotificationSeeder::class);
        $this->actingAsStaff(StaffRole::SuperAdmin);

        $this->get('/dashboard/content')
            ->assertOk()
            ->assertSee(__('aqdar.notifications.tabs.templates'))
            ->assertSee(__('aqdar.notifications.tabs.push'))
            ->assertSee(__('aqdar.notifications.tabs.announcements'))
            ->assertSee(__('aqdar.notifications.tabs.categories'))
            ->assertSee(__('aqdar.catalog.banners'))
            ->assertSee(__('aqdar.notifications.copy_note'))
            ->assertSee('listing_published', false)
            ->assertSee('listing_held', false)
            ->assertSee('listing_suspended', false)
            ->assertSee('staff_new_submission', false)
            ->assertSee('staff_unassigned_lead', false)
            ->assertSee('staff_new_buyer_request', false)
            ->assertSee(__('aqdar.notifications.test_staff'))
            ->assertSee(__('aqdar.notifications.opted_in'))
            ->assertSee(route('dashboard.notifications.test-send'), false);
    }

    public function test_reviewer_cannot_open_notification_desk(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $this->get('/dashboard/content')->assertForbidden();
        $this->post('/dashboard/notifications/test-send', [
            'title_ar' => 'تجربة',
            'body_ar' => 'نص',
        ])->assertForbidden();
    }

    public function test_template_update_rejects_phone_in_copy(): void
    {
        $this->seed(NotificationSeeder::class);
        $this->actingAsStaff(StaffRole::SuperAdmin);
        $template = NotificationTemplate::query()->where('key', 'listing_published')->firstOrFail();

        $this->from('/dashboard/content')
            ->put('/dashboard/notifications/templates/'.$template->id, [
                'title_ar' => 'تم النشر',
                'title_en' => 'Published',
                'body_ar' => 'اتصل على 01012345678',
                'body_en' => 'Call me',
            ])->assertRedirect()
            ->assertSessionHasErrors();
    }

    public function test_staff_test_send_writes_in_app_rows(): void
    {
        $this->seed(NotificationSeeder::class);
        $this->actingAsStaff(StaffRole::SuperAdmin);

        $this->post('/dashboard/notifications/test-send', [
            'title_ar' => 'إرسال تجريبي',
            'title_en' => 'Staff test send',
            'body_ar' => 'هذه رسالة تجريبية للموظفين.',
            'body_en' => 'This is a staff test notification.',
        ])->assertRedirect();

        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'staff_test')
                ->where('title_ar', 'إرسال تجريبي')
                ->where('body_ar', 'هذه رسالة تجريبية للموظفين.')
                ->exists()
        );
    }

    public function test_announcements_categories_and_arabic_banner_tab_match_mockup(): void
    {
        $this->seed(NotificationSeeder::class);
        $this->actingAsStaff(StaffRole::SuperAdmin);
        Category::factory()->create([
            'name_ar' => 'وحدات إعادة البيع',
            'help_ar' => 'نص مساعدة الفئة بدون هاتف.',
            'help_en' => 'Category help with no phone.',
        ]);

        $this->get('/dashboard/content?tab=announcements')
            ->assertOk()
            ->assertSee(__('aqdar.notifications.tabs.announcements'))
            ->assertSee(__('aqdar.notifications.send_push'))
            ->assertSee(__('aqdar.notifications.tabs.log'));

        $this->get('/dashboard/content?tab=categories')
            ->assertOk()
            ->assertSee(__('aqdar.notifications.tabs.categories'))
            ->assertSee('وحدات إعادة البيع')
            ->assertSee('نص مساعدة الفئة بدون هاتف.');

        $this->withSession(['locale' => 'ar'])
            ->get('/dashboard/content?tab=banners')
            ->assertOk()
            ->assertSee('البانرات');
    }
}
