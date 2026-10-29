<?php

namespace Tests\Unit;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Services\MasterDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MasterDataServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_only_one_other_developer_is_allowed(): void
    {
        try {
            app(MasterDataService::class)->createDeveloper([
                'slug' => 'another-other',
                'name_ar' => 'أخرى بديلة',
                'name_en' => 'Another other',
                'is_other' => true,
                'is_active' => true,
            ]);
            $this->fail('Expected ValidationException for a second other developer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_other', $exception->errors());
        }

        $this->assertSame(1, Developer::query()->where('is_other', true)->count());
    }

    public function test_only_one_other_project_is_allowed(): void
    {
        $developer = Developer::query()->where('slug', 'tmg')->firstOrFail();

        try {
            app(MasterDataService::class)->createProject([
                'developer_id' => $developer->id,
                'slug' => 'another-other-project',
                'name_ar' => 'مشروع بديل',
                'name_en' => 'Another other project',
                'is_other' => true,
                'is_active' => true,
            ]);
            $this->fail('Expected ValidationException for a second other project.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_other', $exception->errors());
        }

        $this->assertSame(1, Project::query()->where('is_other', true)->count());
    }

    public function test_updating_the_existing_other_developer_is_allowed(): void
    {
        $other = Developer::query()->where('is_other', true)->firstOrFail();

        $updated = app(MasterDataService::class)->updateDeveloper($other, [
            'name_en' => 'Other option',
            'is_other' => true,
        ]);

        $this->assertSame('Other option', $updated->name_en);
        $this->assertTrue($updated->is_other);
        $this->assertSame(1, Developer::query()->where('is_other', true)->count());
    }

    public function test_delete_retires_instead_of_removing_the_row(): void
    {
        $developer = Developer::query()->where('slug', 'tmg')->firstOrFail();

        app(MasterDataService::class)->deleteDeveloper($developer);

        $this->assertFalse($developer->fresh()->is_active);
        $this->assertDatabaseHas('developers', ['slug' => 'tmg']);
    }

    public function test_cannot_retire_the_controlled_other_developer(): void
    {
        $other = Developer::query()->where('is_other', true)->firstOrFail();

        try {
            app(MasterDataService::class)->deleteDeveloper($other);
            $this->fail('Expected ValidationException when retiring the other developer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_other', $exception->errors());
        }

        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_cannot_retire_the_controlled_other_project(): void
    {
        $other = Project::query()->where('is_other', true)->firstOrFail();

        try {
            app(MasterDataService::class)->deleteProject($other);
            $this->fail('Expected ValidationException when retiring the other project.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_other', $exception->errors());
        }

        $this->assertTrue($other->fresh()->is_active);
    }
}
