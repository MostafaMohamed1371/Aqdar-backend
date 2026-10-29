<?php

namespace Tests\Unit;

use App\Core\Enums\ContentBlockType;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\ContentBlock;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Services\CatalogReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogReadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_can_be_filtered_by_developer(): void
    {
        $tmg = Developer::factory()->create(['slug' => 'tmg-read']);
        $other = Developer::factory()->create(['slug' => 'other-read']);
        Project::factory()->create(['developer_id' => $tmg->id, 'slug' => 'madinaty-read']);
        Project::factory()->create(['developer_id' => $other->id, 'slug' => 'other-project-read']);

        $catalog = app(CatalogReadService::class);
        $filtered = $catalog->projects($tmg->id);

        $this->assertCount(1, $filtered);
        $this->assertSame('madinaty-read', $filtered->first()->slug);
        $this->assertCount(2, $catalog->projects());
    }

    public function test_inactive_projects_and_developers_are_omitted(): void
    {
        $active = Developer::factory()->create(['slug' => 'active-dev']);
        Developer::factory()->create(['slug' => 'retired-dev', 'is_active' => false]);
        Project::factory()->create(['developer_id' => $active->id, 'slug' => 'live-project']);
        Project::factory()->create([
            'developer_id' => $active->id,
            'slug' => 'retired-project',
            'is_active' => false,
        ]);

        $catalog = app(CatalogReadService::class);

        $this->assertFalse($catalog->developers()->pluck('slug')->contains('retired-dev'));
        $this->assertTrue($catalog->developers()->pluck('slug')->contains('active-dev'));
        $this->assertFalse($catalog->projects()->pluck('slug')->contains('retired-project'));
        $this->assertTrue($catalog->projects()->pluck('slug')->contains('live-project'));
    }

    public function test_inactive_areas_are_omitted_from_locations(): void
    {
        $governorate = Governorate::factory()->create(['slug' => 'cairo-read']);
        Area::factory()->create([
            'governorate_id' => $governorate->id,
            'slug' => 'nasr-city-read',
        ]);
        Area::factory()->create([
            'governorate_id' => $governorate->id,
            'slug' => 'retired-area',
            'is_active' => false,
        ]);

        $locations = app(CatalogReadService::class)->locations();
        $cairo = $locations->firstWhere('slug', 'cairo-read');

        $this->assertNotNull($cairo);
        $this->assertTrue($cairo->areas->pluck('slug')->contains('nasr-city-read'));
        $this->assertFalse($cairo->areas->pluck('slug')->contains('retired-area'));
    }

    public function test_inactive_content_is_omitted(): void
    {
        ContentBlock::factory()->create([
            'type' => ContentBlockType::Faq,
            'key' => 'visible-faq',
            'is_active' => true,
        ]);
        ContentBlock::factory()->create([
            'type' => ContentBlockType::Faq,
            'key' => 'hidden-faq',
            'is_active' => false,
        ]);

        $faqs = app(CatalogReadService::class)->content(ContentBlockType::Faq);

        $this->assertTrue($faqs->pluck('key')->contains('visible-faq'));
        $this->assertFalse($faqs->pluck('key')->contains('hidden-faq'));
    }

    public function test_other_developers_are_sorted_last(): void
    {
        Developer::factory()->create(['slug' => 'zeta-dev', 'is_other' => false, 'sort_order' => 50]);
        Developer::factory()->create(['slug' => 'other-dev', 'is_other' => true, 'sort_order' => 1]);
        Developer::factory()->create(['slug' => 'alpha-dev', 'is_other' => false, 'sort_order' => 1]);

        $slugs = app(CatalogReadService::class)->developers()->pluck('slug')->values();

        $this->assertSame('other-dev', $slugs->last());
        $this->assertSame('alpha-dev', $slugs->first());
    }

    public function test_inactive_tags_are_omitted(): void
    {
        Tag::factory()->create(['slug' => 'featured-read']);
        Tag::factory()->create(['slug' => 'retired-tag', 'is_active' => false]);

        $slugs = app(CatalogReadService::class)->tags()->pluck('slug');

        $this->assertTrue($slugs->contains('featured-read'));
        $this->assertFalse($slugs->contains('retired-tag'));
    }

    public function test_other_projects_are_sorted_last(): void
    {
        $developer = Developer::factory()->create(['slug' => 'sort-dev']);
        Project::factory()->create([
            'developer_id' => $developer->id,
            'slug' => 'zeta-project',
            'is_other' => false,
            'sort_order' => 50,
        ]);
        Project::factory()->create([
            'developer_id' => $developer->id,
            'slug' => 'other-project',
            'is_other' => true,
            'sort_order' => 1,
        ]);
        Project::factory()->create([
            'developer_id' => $developer->id,
            'slug' => 'alpha-project',
            'is_other' => false,
            'sort_order' => 1,
        ]);

        $slugs = app(CatalogReadService::class)->projects()->pluck('slug')->values();

        $this->assertSame('other-project', $slugs->last());
        $this->assertSame('alpha-project', $slugs->first());
    }
}
