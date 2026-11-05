<?php

namespace Tests\Integration;

use App\Core\Enums\ContentBlockType;
use App\Core\Support\PublicMediaUrl;
use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Models\Amenity;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\ContentBlock;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Category\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardCatalogCrudTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_super_admin_can_crud_developers_and_projects(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->actingAsStaff();

        $this->post(route('dashboard.developers.store'), [
            'slug' => 'palm-hills',
            'name_ar' => 'بالم هيلز',
            'name_en' => 'Palm Hills',
        ])->assertRedirect(route('dashboard.developers.index'))
            ->assertSessionHas('status');

        $developer = Developer::query()->where('slug', 'palm-hills')->firstOrFail();

        $this->put(route('dashboard.developers.update', $developer), [
            'name_en' => 'Palm Hills Developments',
        ])->assertRedirect(route('dashboard.developers.index'));

        $this->assertDatabaseHas('developers', [
            'slug' => 'palm-hills',
            'name_en' => 'Palm Hills Developments',
            'is_active' => true,
        ]);

        $this->post(route('dashboard.projects.store'), [
            'developer_id' => $developer->id,
            'slug' => 'rehab-city',
            'name_ar' => 'الرحاب',
            'name_en' => 'Al Rehab',
        ])->assertRedirect(route('dashboard.developers.index', ['tab' => 'projects']));

        $project = Project::query()->where('slug', 'rehab-city')->firstOrFail();
        $this->delete(route('dashboard.projects.destroy', $project))
            ->assertRedirect(route('dashboard.developers.index', ['tab' => 'projects']))
            ->assertSessionHas('status');
        $this->assertDatabaseHas('projects', ['slug' => 'rehab-city', 'is_active' => false]);

        $this->delete(route('dashboard.developers.destroy', $developer))
            ->assertRedirect(route('dashboard.developers.index'));
        $this->assertDatabaseHas('developers', ['slug' => 'palm-hills', 'is_active' => false]);
    }

    public function test_master_data_tabs_search_and_project_tab(): void
    {
        $this->actingAsStaff();
        $nile = Developer::factory()->create(['name_en' => 'Nile Developments', 'name_ar' => 'النيل', 'commercial_register' => '584392']);
        Developer::factory()->create(['name_en' => 'Royal Group', 'name_ar' => 'رويال']);
        Project::factory()->create(['developer_id' => $nile->id, 'name_en' => 'Nile Towers', 'name_ar' => 'أبراج النيل']);

        $this->get(route('dashboard.developers.index', ['q' => '584392']))
            ->assertOk()
            ->assertSee('class="ctabs"', false)
            ->assertSee($nile->localizedName())
            ->assertDontSee('Royal Group')
            ->assertDontSee('رويال');

        $this->get(route('dashboard.developers.index', ['tab' => 'projects', 'q' => 'Nile']))
            ->assertOk()
            ->assertSee('أبراج النيل');
    }

    public function test_developer_registry_fields_and_pending_status(): void
    {
        Storage::fake('public_media');
        $this->actingAsStaff();

        $this->post(route('dashboard.developers.store'), [
            'slug' => 'nile-dev',
            'name_ar' => 'النيل للتطوير',
            'name_en' => 'Nile Developments',
            'commercial_register' => '584392',
            'registered_on' => '2024-05-10',
            'logo' => UploadedFile::fake()->image('logo.png', 120, 120),
            'is_verified' => '0',
        ])->assertRedirect(route('dashboard.developers.index'));

        $developer = Developer::query()->where('slug', 'nile-dev')->firstOrFail();
        $this->assertSame('584392', $developer->commercial_register);
        $this->assertSame('2024-05-10', $developer->registered_on->toDateString());
        $this->assertFalse($developer->is_verified);
        Storage::disk('public_media')->assertExists($developer->logo_path);

        $this->get(route('dashboard.developers.index'))
            ->assertOk()
            ->assertSee('584392')
            ->assertSee(__('aqdar.catalog.developer_status.pending'));
        $this->getJson('/api/v1/developers')->assertOk()->assertJsonMissing(['slug' => 'nile-dev']);

        $this->put(route('dashboard.developers.update', $developer), ['is_verified' => '1'])
            ->assertRedirect(route('dashboard.developers.index'));
        $this->assertTrue($developer->fresh()->is_verified);
        $this->getJson('/api/v1/developers')->assertOk()->assertJsonFragment(['slug' => 'nile-dev']);

        $this->post(route('dashboard.developers.store'), [
            'slug' => 'bad-dev',
            'name_ar' => 'س',
            'name_en' => 'Bad',
            'commercial_register' => '12 34; drop',
        ])->assertSessionHasErrors('commercial_register');
    }

    public function test_cities_show_code_area_count_and_can_be_retired(): void
    {
        $this->actingAsStaff();

        $this->post(route('dashboard.governorates.store'), [
            'slug' => 'cairo-test',
            'code' => 'cai',
            'name_ar' => 'القاهرة',
            'name_en' => 'Cairo',
        ])->assertRedirect(route('dashboard.locations.index'));

        $governorate = Governorate::query()->where('slug', 'cairo-test')->firstOrFail();
        $this->assertSame('CAI', $governorate->code);
        Area::query()->create(['governorate_id' => $governorate->id, 'slug' => 'maadi-t', 'name_ar' => 'المعادي', 'name_en' => 'Maadi']);
        Area::query()->create(['governorate_id' => $governorate->id, 'slug' => 'zamalek-t', 'name_ar' => 'الزمالك', 'name_en' => 'Zamalek']);

        $this->get(route('dashboard.locations.index'))
            ->assertOk()
            ->assertSee('CAI')
            ->assertSee(route('dashboard.governorates.destroy', $governorate), false)
            ->assertViewHas('governorates', fn ($rows) => $rows->firstWhere('id', $governorate->id)->areas_count === 2);

        $this->post(route('dashboard.governorates.store'), [
            'slug' => 'cairo-copy',
            'code' => 'CAI',
            'name_ar' => 'نسخة',
            'name_en' => 'Copy',
        ])->assertSessionHasErrors('code');

        $this->delete(route('dashboard.governorates.destroy', $governorate))
            ->assertRedirect(route('dashboard.locations.index'));
        $this->assertFalse($governorate->fresh()->is_active);
    }

    public function test_super_admin_can_crud_locations_unit_types_amenities_tags_and_content(): void
    {
        $this->actingAsStaff();

        $this->post(route('dashboard.governorates.store'), [
            'slug' => 'new-capital',
            'name_ar' => 'العاصمة الإدارية',
            'name_en' => 'New Capital',
        ])->assertRedirect(route('dashboard.locations.index'));

        $governorate = Governorate::query()->where('slug', 'new-capital')->firstOrFail();

        $this->post(route('dashboard.areas.store'), [
            'governorate_id' => $governorate->id,
            'slug' => 'r-seven',
            'name_ar' => 'حي السفارات',
            'name_en' => 'Diplomatic District',
        ])->assertRedirect(route('dashboard.locations.index'));

        $area = Area::query()->where('slug', 'r-seven')->firstOrFail();
        $this->delete(route('dashboard.areas.destroy', $area))
            ->assertRedirect(route('dashboard.locations.index'));
        $this->assertDatabaseHas('areas', ['slug' => 'r-seven', 'is_active' => false]);

        $this->post(route('dashboard.unit-types.store'), [
            'slug' => 'twin-house',
            'name_ar' => 'توين هاوس',
            'name_en' => 'Twin House',
        ])->assertRedirect(route('dashboard.unit-types.index'));
        $this->assertDatabaseHas('unit_types', ['slug' => 'twin-house']);

        $unitType = UnitType::query()->where('slug', 'twin-house')->firstOrFail();
        $this->delete(route('dashboard.unit-types.destroy', $unitType))
            ->assertRedirect(route('dashboard.unit-types.index'));
        $this->assertDatabaseHas('unit_types', ['slug' => 'twin-house', 'is_active' => false]);

        $this->post(route('dashboard.amenities.store'), [
            'slug' => 'clubhouse',
            'name_ar' => 'نادي',
            'name_en' => 'Clubhouse',
        ])->assertRedirect(route('dashboard.amenities.index'));
        $amenity = Amenity::query()->where('slug', 'clubhouse')->firstOrFail();
        $this->delete(route('dashboard.amenities.destroy', $amenity))
            ->assertRedirect(route('dashboard.amenities.index'));
        $this->assertDatabaseHas('amenities', ['slug' => 'clubhouse', 'is_active' => false]);

        $this->post(route('dashboard.tags.store'), [
            'slug' => 'hot-deal',
            'name_ar' => 'عرض ساخن',
            'name_en' => 'Hot deal',
        ])->assertRedirect(route('dashboard.tags.index'));
        $tag = Tag::query()->where('slug', 'hot-deal')->firstOrFail();
        $this->put(route('dashboard.tags.update', $tag), [
            'name_en' => 'Hot Deal',
        ])->assertRedirect(route('dashboard.tags.index'));
        $this->assertDatabaseHas('tags', ['slug' => 'hot-deal', 'name_en' => 'Hot Deal', 'is_active' => true]);
        $this->delete(route('dashboard.tags.destroy', $tag))
            ->assertRedirect(route('dashboard.tags.index'));
        $this->assertDatabaseHas('tags', ['slug' => 'hot-deal', 'is_active' => false]);

        $this->post(route('dashboard.content.store'), [
            'type' => ContentBlockType::Faq->value,
            'key' => 'phase-two-faq',
            'title_ar' => 'سؤال المرحلة',
            'title_en' => 'Phase question',
            'body_ar' => 'جواب عربي',
            'body_en' => 'English answer',
        ])->assertRedirect(route('dashboard.content.index'));

        $block = ContentBlock::query()->where('key', 'phase-two-faq')->firstOrFail();
        $this->put(route('dashboard.content.update', $block), [
            'title_en' => 'Updated question',
        ])->assertRedirect(route('dashboard.content.index'));
        $this->assertDatabaseHas('content_blocks', [
            'key' => 'phase-two-faq',
            'title_en' => 'Updated question',
            'is_active' => true,
        ]);

        $this->delete(route('dashboard.content.destroy', $block))
            ->assertRedirect(route('dashboard.content.index'));
        $this->assertDatabaseHas('content_blocks', ['key' => 'phase-two-faq', 'is_active' => false]);
    }

    public function test_invalid_developer_name_is_rejected(): void
    {
        $this->actingAsStaff();

        $this->from(route('dashboard.developers.index'))
            ->post(route('dashboard.developers.store'), [
                'slug' => 'bad-name',
                'name_ar' => 'Dev2',
                'name_en' => 'Developer Two',
            ])->assertRedirect()
            ->assertSessionHasErrors('name_ar');
    }

    public function test_cannot_retire_the_controlled_other_developer(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->actingAsStaff();

        $other = Developer::query()->where('is_other', true)->firstOrFail();

        $this->from(route('dashboard.developers.index'))
            ->delete(route('dashboard.developers.destroy', $other))
            ->assertRedirect()
            ->assertSessionHasErrors('is_other');

        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_cannot_retire_the_controlled_other_project(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->actingAsStaff();

        $other = Project::query()->where('is_other', true)->firstOrFail();

        $this->from(route('dashboard.developers.index'))
            ->delete(route('dashboard.projects.destroy', $other))
            ->assertRedirect()
            ->assertSessionHasErrors('is_other');

        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_destroying_a_category_retires_it(): void
    {
        $this->actingAsStaff();

        $this->post(route('dashboard.categories.store'), [
            'name_ar' => 'عرض موسمي',
            'name_en' => 'Seasonal',
            'slug' => 'seasonal',
        ])->assertRedirect(route('dashboard.categories.index'));

        $category = Category::query()->where('slug', 'seasonal')->firstOrFail();

        $this->delete(route('dashboard.categories.destroy', $category))
            ->assertRedirect(route('dashboard.categories.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('categories', ['slug' => 'seasonal', 'is_active' => false]);
        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'seasonal']);
    }

    public function test_toggling_area_active_does_not_clear_governorate(): void
    {
        $this->actingAsStaff();

        $governorate = Governorate::factory()->create(['slug' => 'toggle-gov']);
        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'slug' => 'toggle-area',
            'is_active' => true,
        ]);

        $this->put(route('dashboard.areas.update', $area), [
            'is_active' => '0',
        ])->assertRedirect(route('dashboard.locations.index'));

        $area->refresh();
        $this->assertFalse($area->is_active);
        $this->assertSame($governorate->id, $area->governorate_id);

        $this->put(route('dashboard.governorates.update', $governorate), [
            'is_active' => '0',
        ])->assertRedirect(route('dashboard.locations.index'));
        $this->assertFalse($governorate->fresh()->is_active);
    }

    public function test_banner_image_is_stored_on_public_media_and_exposed_as_url(): void
    {
        Storage::fake('public_media');
        $this->actingAsStaff();

        $this->post(route('dashboard.content.store'), [
            'type' => ContentBlockType::Banner->value,
            'key' => 'home-hero-test',
            'title_ar' => 'بنر اختبار',
            'title_en' => 'Test banner',
            'link_url' => 'https://example.com/promo',
            'sort_order' => 3,
            'image' => UploadedFile::fake()->image('hero.jpg', 800, 400),
        ])->assertRedirect(route('dashboard.content.index'));

        $block = ContentBlock::query()->where('key', 'home-hero-test')->firstOrFail();
        $this->assertNotNull($block->image_path);
        Storage::disk('public_media')->assertExists($block->image_path);

        $this->assertSame('https://example.com/promo', $block->link_url);
        $this->assertSame(3, $block->sort_order);

        $this->getJson('/api/v1/content/banners')
            ->assertOk()
            ->assertJsonFragment(['key' => 'home-hero-test', 'link_url' => 'https://example.com/promo'])
            ->assertJsonPath('data.0.image_url', PublicMediaUrl::for($block->image_path));
    }
}
