<?php

namespace App\Modules\Category\Http\Resources;

use App\Modules\Category\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->localizedName(),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'kind' => $this->kind?->value,
            'segment' => $this->segment?->value,
            'distressed_child' => $this->distressed_child?->value,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'help' => app()->getLocale() === 'en'
                ? ($this->help_en ?: $this->help_ar)
                : ($this->help_ar ?: $this->help_en),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
