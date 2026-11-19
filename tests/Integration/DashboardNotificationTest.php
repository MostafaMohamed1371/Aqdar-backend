<?php

namespace Tests\Integration;

use App\Core\Enums\StaffRole;
use App\Modules\Category\Models\Category;
use App\Modules\Notification\Database\Seeders\NotificationSeeder;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Notification\Services\Push\PushGateway;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_push_tab_shows_fcm_status_and_actions(): void
    {
        $this->seed(NotificationSeeder::class);
        $this->actingAsStaff(StaffRole::SuperAdmin);

        $this->get('/dashboard/content?tab=push')
            ->assertOk()
            ->assertSee(__('aqdar.notifications.fcm_status_title'))
            ->assertSee(__('aqdar.notifications.fcm_verify'))
            ->assertSee(__('aqdar.notifications.fcm_diagnostic'))
            ->assertSee(route('dashboard.notifications.fcm-verify'), false)
            ->assertSee(route('dashboard.notifications.fcm-diagnostic'), false);
    }

    public function test_fcm_verify_requires_fcm_driver(): void
    {
        config(['aqdar.notifications.push_driver' => 'log']);
        $this->actingAsStaff(StaffRole::SuperAdmin);

        $this->from('/dashboard/content?tab=push')
            ->post('/dashboard/notifications/fcm-verify')
            ->assertRedirect(route('dashboard.content.index', ['tab' => 'push']))
            ->assertSessionHas('error', __('aqdar.notifications.fcm_verify_driver'));
    }

    public function test_fcm_diagnostic_sends_to_latest_device_token(): void
    {
        $this->configureFcm();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.desk',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/v1/projects/aqdar-eg/messages:send' => Http::response([
                'name' => 'projects/aqdar-eg/messages/desk-1',
            ]),
        ]);

        $buyer = User::factory()->create();
        $buyer->devices()->create([
            'device_uuid' => 'ios-desk-diag',
            'platform' => 'ios',
            'push_token' => 'desk-fcm-token',
            'last_seen_at' => now(),
        ]);

        $this->actingAsStaff(StaffRole::SuperAdmin);

        $this->from('/dashboard/content?tab=push')
            ->post('/dashboard/notifications/fcm-diagnostic', [
                'title_ar' => 'اختبار عَقدار',
                'title_en' => 'AQDAR test',
                'body_ar' => 'إذا ظهرت هذه الرسالة، فإشعارات الدفع تعمل.',
                'body_en' => 'If you see this, FCM delivery works.',
            ])
            ->assertRedirect(route('dashboard.content.index', ['tab' => 'push']))
            ->assertSessionHas('status');

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'messages:send')) {
                return false;
            }

            return ($request->data()['message']['token'] ?? null) === 'desk-fcm-token';
        });
    }

    private function configureFcm(): void
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
            'aqdar.notifications.fcm.credentials' => '',
        ]);
        $this->app->forgetInstance(PushGateway::class);
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
