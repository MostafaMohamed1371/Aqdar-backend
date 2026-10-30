<?php

namespace Tests\Integration;

use App\Core\Enums\StaffRole;
use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Project;
use App\Modules\Category\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class CatalogPhase2Test extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_uat02_distressed_has_exactly_two_active_children(): void
    {
        $this->seed(CatalogSeeder::class);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk();
        $tree = $response->json('data');
        $distressed = collect($tree)->firstWhere('slug', 'distressed');

        $this->assertNotNull($distressed);
        $this->assertCount(2, $distressed['children']);
        $this->assertEqualsCanonicalizing(
            ['a', 'b'],
            array_column($distressed['children'], 'distressed_child'),
        );
        $this->assertTrue(collect($tree)->contains('slug', 'non-distressed'));
    }

    public function test_cannot_add_a_third_distressed_child(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->actingAsStaff();

        $parent = Category::query()->where('slug', 'distressed')->firstOrFail();

        $this->from(route('dashboard.categories.create'))
            ->post(route('dashboard.categories.store'), [
                'name' => 'Type C',
                'slug' => 'distressed-c',
                'parent_id' => $parent->id,
            ])->assertRedirect()
            ->assertSessionHasErrors('parent_id');
    }

    public function test_cannot_deactivate_a_distressed_child(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->actingAsStaff();

        $child = Category::query()->where('slug', 'distressed-a')->firstOrFail();

        $this->from(route('dashboard.categories.edit', $child))
            ->put(route('dashboard.categories.update', $child), [
                'is_active' => '0',
            ])->assertRedirect()
            ->assertSessionHasErrors('is_active');
    }

    public function test_public_master_data_hides_inactive_records(): void
    {
        $this->seed(CatalogSeeder::class);
        Developer::query()->where('slug', 'tmg')->update(['is_active' => false]);

        $this->getJson('/api/v1/developers')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'tmg']);

        $this->getJson('/api/v1/locations')->assertOk()->assertJsonFragment(['slug' => 'cairo']);
        $this->getJson('/api/v1/unit-types')->assertOk()->assertJsonFragment(['slug' => 'apartment']);
        $this->getJson('/api/v1/amenities')->assertOk()->assertJsonFragment(['slug' => 'parking']);
        $this->getJson('/api/v1/tags')->assertOk()->assertJsonFragment(['slug' => 'featured']);
        $this->getJson('/api/v1/content/faqs')->assertOk()->assertJsonFragment(['key' => 'what-is-distressed']);
        $this->getJson('/api/v1/content/banners')->assertOk();
        $this->getJson('/api/v1/content/support')->assertOk();
        $this->getJson('/api/v1/projects')->assertOk()->assertJsonFragment(['is_other' => true]);
    }

    public function test_non_super_admin_roles_cannot_open_phase2_admin_screens(): void
    {
        foreach ([StaffRole::Operations, StaffRole::Reviewer, StaffRole::Sales, StaffRole::Finance] as $role) {
            $this->actingAsStaff($role);

            $this->get('/dashboard/developers')->assertForbidden();
            if ($role === StaffRole::Operations) {
                $this->get('/dashboard/content')->assertOk();
            } else {
                $this->get('/dashboard/content')->assertForbidden();
            }
            $this->get('/dashboard/locations')->assertForbidden();
            $this->get('/dashboard/unit-types')->assertForbidden();
            $this->get('/dashboard/amenities')->assertForbidden();
            $this->get('/dashboard/tags')->assertForbidden();
            $this->get('/dashboard/categories')->assertForbidden();
        }
    }

    public function test_super_admin_can_open_adm_05_11_15(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->actingAsStaff();

        $this->get('/dashboard/developers')->assertOk()->assertSee('ADM-05', false);
        $this->get('/dashboard/content')->assertOk()->assertSee('ADM-11', false);
        $this->get('/dashboard/categories')->assertOk()->assertSee('ADM-15', false);
        $this->get('/dashboard/locations')->assertOk()->assertSee('ADM-15', false);
        $this->get('/dashboard/unit-types')->assertOk()->assertSee('ADM-15', false);
        $this->get('/dashboard/amenities')->assertOk()->assertSee('ADM-15', false);
        $this->get('/dashboard/tags')->assertOk()->assertSee('ADM-15', false);
    }

    public function test_only_one_other_developer_is_allowed(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->actingAsStaff();

        $this->from(route('dashboard.developers.index'))
            ->post(route('dashboard.developers.store'), [
                'slug' => 'another-other',
                'name_ar' => 'أخرى بديلة',
                'name_en' => 'Another other',
                'is_other' => '1',
            ])->assertRedirect()
            ->assertSessionHasErrors('is_other');
    }

    public function test_only_one_other_project_is_allowed(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->actingAsStaff();

        $developer = Developer::query()->where('slug', 'tmg')->firstOrFail();

        $this->from(route('dashboard.developers.index'))
            ->post(route('dashboard.projects.store'), [
                'developer_id' => $developer->id,
                'slug' => 'another-other-project',
                'name_ar' => 'مشروع بديل',
                'name_en' => 'Another other project',
                'is_other' => '1',
            ])->assertRedirect()
            ->assertSessionHasErrors('is_other');

        $this->assertSame(1, Project::query()->where('is_other', true)->count());
    }

    public function test_cannot_deactivate_distressed_child_b(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->actingAsStaff();

        $child = Category::query()->where('slug', 'distressed-b')->firstOrFail();

        $this->from(route('dashboard.categories.edit', $child))
            ->put(route('dashboard.categories.update', $child), [
                'is_active' => '0',
            ])->assertRedirect()
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($child->fresh()->is_active);
    }

    public function test_cannot_delete_or_rename_protected_category_slugs(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->actingAsStaff();

        foreach (Category::PROTECTED_SLUGS as $slug) {
            $category = Category::query()->where('slug', $slug)->firstOrFail();

            $this->from(route('dashboard.categories.index'))
                ->delete(route('dashboard.categories.destroy', $category))
                ->assertRedirect()
                ->assertSessionHasErrors('slug');

            $this->from(route('dashboard.categories.edit', $category))
                ->put(route('dashboard.categories.update', $category), [
                    'slug' => $slug.'-hacked',
                ])->assertRedirect()
                ->assertSessionHasErrors('slug');

            $this->assertDatabaseHas('categories', ['id' => $category->id, 'slug' => $slug]);
        }
    }

    public function test_public_tree_hides_inactive_categories(): void
    {
        $this->seed(CatalogSeeder::class);

        Category::factory()->inactive()->create([
            'slug' => 'hidden-root',
            'name' => 'Hidden root',
            'parent_id' => null,
        ]);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'hidden-root']);
    }

    public function test_seed_includes_twenty_seven_governorates(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->getJson('/api/v1/locations')
            ->assertOk()
            ->assertJsonCount(27, 'data');
    }

    public function test_projects_can_be_filtered_by_developer_id(): void
    {
        $this->seed(CatalogSeeder::class);

        $tmg = Developer::query()->where('slug', 'tmg')->firstOrFail();

        $this->getJson('/api/v1/projects?developer_id='.$tmg->id)
            ->assertOk()
            ->assertJsonFragment(['slug' => 'madinaty'])
            ->assertJsonMissing(['slug' => 'other']);
    }

    public function test_inactive_areas_are_hidden_from_public_locations(): void
    {
        $this->seed(CatalogSeeder::class);
        Area::query()->where('slug', 'maadi')->update(['is_active' => false]);

        $this->getJson('/api/v1/locations')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'nasr-city'])
            ->assertJsonMissing(['slug' => 'maadi']);
    }

    public function test_catalog_names_follow_accept_language(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/v1/developers')
            ->assertOk()
            ->assertJsonFragment(['name' => 'طلعت مصطفى']);

        $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/developers')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Talaat Moustafa Group']);

        $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Distressed']);
    }

    public function test_marketplace_cannot_write_catalog_resources(): void
    {
        $this->postJson('/api/v1/developers', ['slug' => 'hack'])->assertMethodNotAllowed();
        $this->postJson('/api/v1/locations', ['slug' => 'hack'])->assertMethodNotAllowed();
        $this->postJson('/api/v1/unit-types', ['slug' => 'hack'])->assertMethodNotAllowed();
        $this->postJson('/api/v1/amenities', ['slug' => 'hack'])->assertMethodNotAllowed();
        $this->postJson('/api/v1/content/faqs', ['slug' => 'hack'])->assertMethodNotAllowed();
        $this->postJson('/api/v1/content/banners', ['slug' => 'hack'])->assertMethodNotAllowed();
        $this->postJson('/api/v1/content/support', ['slug' => 'hack'])->assertMethodNotAllowed();
        $this->postJson('/api/v1/projects', ['slug' => 'hack'])->assertMethodNotAllowed();
        $this->postJson('/api/v1/tags', ['slug' => 'hack'])->assertMethodNotAllowed();
    }

    public function test_controlled_other_developer_is_listed_last(): void
    {
        $this->seed(CatalogSeeder::class);

        $slugs = collect($this->getJson('/api/v1/developers')->json('data'))->pluck('slug');

        $this->assertTrue($slugs->contains('tmg'));
        $this->assertSame('other', $slugs->last());

        $projectSlugs = collect($this->getJson('/api/v1/projects')->json('data'))->pluck('slug');
        $this->assertTrue($projectSlugs->contains('madinaty'));
        $this->assertSame('other', $projectSlugs->last());
    }

    public function test_distressed_children_expose_help_copy(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonFragment(['help' => 'المالك لديه أقساط متأخرة أو خطر على العقد.'])
            ->assertJsonFragment(['help' => 'المالك يتوقع عدم القدرة على استكمال الأقساط.']);

        $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonFragment(['help' => 'The owner has overdue installments or contract risk.']);
    }

    public function test_retired_developer_is_hidden_from_public_list(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->actingAsStaff();

        $developer = Developer::query()->where('slug', 'tmg')->firstOrFail();

        $this->delete(route('dashboard.developers.destroy', $developer))
            ->assertRedirect(route('dashboard.developers.index'));

        $this->assertDatabaseHas('developers', ['slug' => 'tmg', 'is_active' => false]);
        $this->getJson('/api/v1/developers')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'tmg']);
    }
}
