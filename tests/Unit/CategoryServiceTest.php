<?php

namespace Tests\Unit;

use App\Core\Enums\CategoryKind;
use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Category\Models\Category;
use App\Modules\Category\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_create_rejects_a_third_distressed_child(): void
    {
        $parent = Category::query()->where('slug', 'distressed')->firstOrFail();

        $this->expectException(ValidationException::class);

        app(CategoryService::class)->create([
            'name' => 'Type C',
            'name_ar' => 'نوع ج',
            'name_en' => 'Type C',
            'slug' => 'distressed-c',
            'parent_id' => $parent->id,
            'kind' => CategoryKind::DistressedChild,
        ]);
    }

    public function test_update_rejects_deactivating_a_distressed_child(): void
    {
        $child = Category::query()->where('slug', 'distressed-b')->firstOrFail();

        try {
            app(CategoryService::class)->update($child->id, ['is_active' => false]);
            $this->fail('Expected ValidationException when deactivating a distressed child.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_active', $exception->errors());
        }

        $this->assertTrue($child->fresh()->is_active);
    }

    public function test_update_rejects_changing_a_protected_slug(): void
    {
        $category = Category::query()->where('slug', 'non-distressed')->firstOrFail();

        try {
            app(CategoryService::class)->update($category->id, ['slug' => 'non-distressed-hacked']);
            $this->fail('Expected ValidationException when changing a protected slug.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'slug' => 'non-distressed']);
    }

    public function test_delete_rejects_protected_slugs(): void
    {
        $category = Category::query()->where('slug', 'distressed-a')->firstOrFail();

        try {
            app(CategoryService::class)->delete($category->id);
            $this->fail('Expected ValidationException when deleting a protected category.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }

        $this->assertDatabaseHas('categories', ['slug' => 'distressed-a', 'is_active' => true]);
    }

    public function test_delete_retires_an_unprotected_category(): void
    {
        $category = Category::factory()->create([
            'slug' => 'seasonal-offer',
            'name' => 'Seasonal offer',
            'kind' => CategoryKind::Topic,
            'parent_id' => null,
            'is_active' => true,
        ]);

        $this->assertTrue(app(CategoryService::class)->delete($category->id));
        $this->assertDatabaseHas('categories', ['slug' => 'seasonal-offer', 'is_active' => false]);
    }

    public function test_tree_active_omits_inactive_roots_and_children(): void
    {
        Category::factory()->inactive()->create([
            'slug' => 'hidden-root',
            'name' => 'Hidden root',
            'kind' => CategoryKind::Topic,
            'parent_id' => null,
        ]);

        $nonDistressed = Category::query()->where('slug', 'non-distressed')->firstOrFail();
        Category::factory()->inactive()->create([
            'slug' => 'hidden-child',
            'name' => 'Hidden child',
            'kind' => CategoryKind::Topic,
            'parent_id' => $nonDistressed->id,
        ]);

        $tree = app(CategoryService::class)->treeActive();
        $slugs = $tree->pluck('slug');
        $nonDistressedNode = $tree->firstWhere('slug', 'non-distressed');

        $this->assertFalse($slugs->contains('hidden-root'));
        $this->assertNotNull($nonDistressedNode);
        $this->assertFalse($nonDistressedNode->children->pluck('slug')->contains('hidden-child'));
    }
}
