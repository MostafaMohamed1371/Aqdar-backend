<?php

namespace App\Modules\Catalog\Services;

use App\Core\Enums\ContentBlockType;
use App\Modules\Catalog\Models\Amenity;
use App\Modules\Catalog\Models\ContentBlock;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Models\UnitType;
use Illuminate\Database\Eloquent\Collection;

class CatalogReadService
{
    /** @return Collection<int, Governorate> */
    public function locations(): Collection
    {
        return Governorate::query()
            ->where('is_active', true)
            ->with(['areas' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    /** @return Collection<int, Developer> */
    public function developers(): Collection
    {
        return Developer::query()
            ->where('is_active', true)
            ->orderBy('is_other')
            ->orderBy('sort_order')
            ->get();
    }

    /** @return Collection<int, Project> */
    public function projects(?int $developerId = null): Collection
    {
        return Project::query()
            ->where('is_active', true)
            ->when($developerId !== null, fn ($query) => $query->where('developer_id', $developerId))
            ->orderBy('is_other')
            ->orderBy('sort_order')
            ->get();
    }

    /** @return Collection<int, UnitType> */
    public function unitTypes(): Collection
    {
        return UnitType::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    /** @return Collection<int, Amenity> */
    public function amenities(): Collection
    {
        return Amenity::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    /** @return Collection<int, Tag> */
    public function tags(): Collection
    {
        return Tag::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    /** @return Collection<int, ContentBlock> */
    public function content(ContentBlockType $type): Collection
    {
        return ContentBlock::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
