<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Amenity;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\ContentBlock;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Category\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MasterDataService
{
    /**
     * Record counts for the master-data tabs (ADM-05).
     *
     * @return array{developers: int, projects: int, locations: int, unit-types: int, categories: int, amenities: int, tags: int}
     */
    public function counts(): array
    {
        return [
            'developers' => Developer::query()->count(),
            'projects' => Project::query()->count(),
            'locations' => Governorate::query()->count(),
            'unit-types' => UnitType::query()->count(),
            'categories' => Category::query()->count(),
            'amenities' => Amenity::query()->count(),
            'tags' => Tag::query()->count(),
        ];
    }

    public function createGovernorate(array $data): Governorate
    {
        return Governorate::query()->create($data);
    }

    public function updateGovernorate(Governorate $governorate, array $data): Governorate
    {
        $governorate->fill($data)->save();

        return $governorate;
    }

    public function deleteGovernorate(Governorate $governorate): void
    {
        $this->retire($governorate);
    }

    public function createArea(array $data): Area
    {
        return Area::query()->create($data);
    }

    public function updateArea(Area $area, array $data): Area
    {
        $area->fill($data)->save();

        return $area;
    }

    public function deleteArea(Area $area): void
    {
        $this->retire($area);
    }

    public function createDeveloper(array $data, ?UploadedFile $logo = null): Developer
    {
        $this->assertSingleOther(Developer::class, $data);
        if ($logo !== null) {
            $data['logo_path'] = $logo->store('developers', 'public_media');
        }

        return Developer::query()->create($data);
    }

    public function updateDeveloper(Developer $developer, array $data, ?UploadedFile $logo = null): Developer
    {
        $this->assertSingleOther(Developer::class, $data, $developer->id);
        if ($logo !== null) {
            $this->deleteBannerImage($developer->logo_path);
            $data['logo_path'] = $logo->store('developers', 'public_media');
        }
        $developer->fill($data)->save();

        return $developer;
    }

    public function deleteDeveloper(Developer $developer): void
    {
        $this->retire($developer);
    }

    public function createProject(array $data): Project
    {
        $this->assertSingleOther(Project::class, $data);

        return Project::query()->create($data);
    }

    public function updateProject(Project $project, array $data): Project
    {
        $this->assertSingleOther(Project::class, $data, $project->id);
        $project->fill($data)->save();

        return $project;
    }

    public function deleteProject(Project $project): void
    {
        $this->retire($project);
    }

    public function createUnitType(array $data): UnitType
    {
        return UnitType::query()->create($data);
    }

    public function updateUnitType(UnitType $unitType, array $data): UnitType
    {
        $unitType->fill($data)->save();

        return $unitType;
    }

    public function deleteUnitType(UnitType $unitType): void
    {
        $this->retire($unitType);
    }

    public function createAmenity(array $data): Amenity
    {
        return Amenity::query()->create($data);
    }

    public function updateAmenity(Amenity $amenity, array $data): Amenity
    {
        $amenity->fill($data)->save();

        return $amenity;
    }

    public function deleteAmenity(Amenity $amenity): void
    {
        $this->retire($amenity);
    }

    public function createTag(array $data): Tag
    {
        return Tag::query()->create($data);
    }

    public function updateTag(Tag $tag, array $data): Tag
    {
        $tag->fill($data)->save();

        return $tag;
    }

    public function deleteTag(Tag $tag): void
    {
        $this->retire($tag);
    }

    public function createContent(array $data, ?UploadedFile $image = null): ContentBlock
    {
        if ($image !== null) {
            $data['image_path'] = $this->storeBannerImage($image);
        }

        return ContentBlock::query()->create($data);
    }

    public function updateContent(ContentBlock $block, array $data, ?UploadedFile $image = null): ContentBlock
    {
        if ($image !== null) {
            $this->deleteBannerImage($block->image_path);
            $data['image_path'] = $this->storeBannerImage($image);
        }

        $block->fill($data)->save();

        return $block;
    }

    public function deleteContent(ContentBlock $block): void
    {
        $this->retire($block);
    }

    public function retire(Model $model): void
    {
        if (($model->getAttribute('is_other') ?? false) === true) {
            throw ValidationException::withMessages([
                'is_other' => [__('aqdar.catalog.cannot_retire_other')],
            ]);
        }

        $model->forceFill(['is_active' => false])->save();
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $data
     */
    private function assertSingleOther(string $model, array $data, ?int $ignoreId = null): void
    {
        if (! ($data['is_other'] ?? false)) {
            return;
        }

        $exists = $model::query()
            ->where('is_other', true)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'is_other' => [__('aqdar.catalog.single_other')],
            ]);
        }
    }

    private function storeBannerImage(UploadedFile $image): string
    {
        $path = $image->store('banners', 'public_media');

        if (! is_string($path) || $path === '' || ! Storage::disk('public_media')->exists($path)) {
            throw ValidationException::withMessages([
                'image' => [__('aqdar.catalog.image_store_failed')],
            ]);
        }

        return $path;
    }

    private function deleteBannerImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public_media')->delete($path);
        }
    }
}
