<?php

namespace Tests\Integration;

use App\Modules\Category\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardCatalogTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_guest_cannot_access_settings_dashboard(): void
    {
        $this->get('/dashboard/settings')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_manage_settings_in_dashboard(): void
    {
        $this->actingAsStaff();

        $this->get('/dashboard/settings')
            ->assertOk()
            ->assertSee(__('aqdar.menu.adm_13'))
            ->assertSee(__('aqdar.settings.platform_name'));

        $this->put('/dashboard/settings', [
            'platform_name' => 'AQDAR Desk',
            'support_email' => 'support@aqdar.eg',
            'sales_name' => 'فريق مبيعات عَقدار',
            'sales_name_en' => 'AQDAR Sales Team',
            'sales_phone' => '19991',
            'sales_hours' => 'يومياً 10ص – 10م',
            'sales_hours_en' => 'Daily 10am – 10pm',
            'lead_assignment_rule' => 'manual',
            'maintenance_mode' => '0',
            'feature_smart_notifications' => '0',
            'commission_default_rate' => '2.5',
            'audit_retention_months' => '24',
            'notification_quiet_hours' => '',
            'default_locale' => 'ar',
        ])
            ->assertRedirect(route('dashboard.settings.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('settings', ['key' => 'platform_name', 'value' => 'AQDAR Desk']);
        $this->assertDatabaseHas('settings', ['key' => 'sales_phone', 'value' => '19991']);
    }

    public function test_authenticated_user_can_manage_categories_in_dashboard(): void
    {
        $this->actingAsStaff();

        $this->get('/dashboard/categories')
            ->assertOk()
            ->assertSee('ADM-15', false);

        $this->post('/dashboard/categories', [
            'name' => 'Electronics',
            'slug' => 'electronics',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect(route('dashboard.categories.index'));

        $category = Category::query()->where('slug', 'electronics')->firstOrFail();

        $this->get('/dashboard/categories/'.$category->id.'/edit')
            ->assertOk()
            ->assertSee('Electronics');

        $this->put('/dashboard/categories/'.$category->id, [
            'name_ar' => 'Electronics Plus',
            'name_en' => 'Electronics Plus',
            'help_ar' => 'مساعدة التصنيف',
            'help_en' => 'Category help',
        ])->assertRedirect(route('dashboard.categories.index'));

        $this->assertDatabaseHas('categories', [
            'slug' => 'electronics',
            'name' => 'Electronics Plus',
            'help_ar' => 'مساعدة التصنيف',
            'help_en' => 'Category help',
        ]);
    }

    public function test_dashboard_uses_light_desk_without_dark_toggle(): void
    {
        $this->actingAsStaff();

        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('data-theme-toggle', false)
            ->assertSee('color-scheme', false)
            ->assertSee('content="light"', false)
            ->assertSee('structer-theme', false);
    }

    public function test_login_page_forces_light_color_scheme(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('data-theme-toggle', false)
            ->assertDontSee('prefers-color-scheme', false)
            ->assertSee('structer-theme', false)
            ->assertSee('root.classList.remove', false)
            ->assertSee('content="light"', false);
    }
}
