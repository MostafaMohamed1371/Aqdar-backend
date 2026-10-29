<?php

namespace Tests\Integration;

use App\Modules\Category\Models\Category;
use App\Modules\Category\Services\CategoryService;
use App\Modules\Setting\Models\Setting;
use App\Modules\Setting\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CachedCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_list_is_cached(): void
    {
        Cache::flush();
        Setting::factory()->count(2)->create();

        $service = app(SettingService::class);

        DB::enableQueryLog();
        $first = $service->all();
        $queriesAfterFirst = count(DB::getQueryLog());

        DB::flushQueryLog();
        $second = $service->all();
        $queriesAfterSecond = count(DB::getQueryLog());

        $this->assertCount(2, $first);
        $this->assertCount(2, $second);
        $this->assertSame(1, $queriesAfterFirst);
        $this->assertSame(0, $queriesAfterSecond);
    }

    public function test_setting_cache_is_invalidated_on_update(): void
    {
        Cache::flush();
        $setting = Setting::factory()->create(['key' => 'site_name', 'value' => 'Old']);

        $service = app(SettingService::class);
        $service->all();
        $service->update($setting->id, ['value' => 'New']);

        $refreshed = $service->all()->firstWhere('key', 'site_name');

        $this->assertSame('New', $refreshed?->value);
        $this->assertSame('New', $service->findByKey('site_name')?->value);
    }

    public function test_categories_list_is_cached(): void
    {
        Cache::flush();
        Category::factory()->count(3)->create();
        Category::factory()->inactive()->create();

        $service = app(CategoryService::class);

        DB::enableQueryLog();
        $first = $service->listActive();
        $queriesAfterFirst = count(DB::getQueryLog());

        DB::flushQueryLog();
        $second = $service->listActive();
        $queriesAfterSecond = count(DB::getQueryLog());

        $this->assertCount(3, $first);
        $this->assertCount(3, $second);
        $this->assertSame(1, $queriesAfterFirst);
        $this->assertSame(0, $queriesAfterSecond);
    }

    public function test_public_config_api_is_available(): void
    {
        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.currency', 'EGP');
    }

    public function test_public_categories_api_lists_active_only(): void
    {
        Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        Category::factory()->inactive()->create(['name' => 'Hidden', 'slug' => 'hidden']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'electronics');
    }
}
