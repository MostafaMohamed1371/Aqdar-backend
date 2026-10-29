<?php

namespace Tests\Integration;

use App\Modules\Category\Models\Category;
use App\Modules\Setting\Models\Setting;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogApiLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_or_write_settings_api(): void
    {
        $this->getJson('/api/v1/settings')->assertUnauthorized();
        $this->getJson('/api/v1/settings/key/app_locale')->assertUnauthorized();
        $this->postJson('/api/v1/settings', [
            'key' => 'support_email',
            'value' => 'help@example.com',
            'group' => 'general',
        ])->assertUnauthorized();
    }

    public function test_authenticated_marketplace_user_cannot_use_settings_api(): void
    {
        $setting = Setting::factory()->create(['key' => 'app_locale', 'value' => 'ar']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/settings')->assertForbidden();
        $this->getJson('/api/v1/settings/key/app_locale')->assertForbidden();
        $this->postJson('/api/v1/settings', [
            'key' => 'support_email',
            'value' => 'help@example.com',
            'group' => 'general',
        ])->assertForbidden();
        $this->putJson('/api/v1/settings/'.$setting->id, [
            'value' => 'en',
        ])->assertForbidden();
        $this->deleteJson('/api/v1/settings/'.$setting->id)->assertForbidden();
    }

    public function test_public_config_still_works(): void
    {
        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.currency', 'EGP')
            ->assertJsonStructure(['data' => ['sales_contact' => ['name', 'phone', 'hours']]]);
    }

    public function test_guest_can_list_categories_but_cannot_write(): void
    {
        Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'electronics');

        $this->postJson('/api/v1/categories', [
            'name' => 'Hidden Write',
            'slug' => 'hidden-write',
        ])->assertUnauthorized();
    }

    public function test_authenticated_marketplace_user_cannot_write_categories(): void
    {
        $category = Category::factory()->create(['slug' => 'electronics']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/categories', [
            'name' => 'Forbidden',
            'slug' => 'forbidden',
        ])->assertForbidden();

        $this->putJson('/api/v1/categories/'.$category->id, [
            'name' => 'Hacked',
        ])->assertForbidden();

        $this->deleteJson('/api/v1/categories/'.$category->id)->assertForbidden();
    }
}
