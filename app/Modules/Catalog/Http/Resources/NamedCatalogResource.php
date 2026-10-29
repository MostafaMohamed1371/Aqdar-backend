<?php

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Illuminate\Database\Eloquent\Model */
class NamedCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->localizedName(),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'is_active' => $this->is_active,
            'is_other' => $this->is_other ?? false,
            'sort_order' => $this->sort_order,
            'developer_id' => $this->developer_id ?? null,
            'governorate_id' => $this->governorate_id ?? null,
            'areas' => NamedCatalogResource::collection($this->whenLoaded('areas')),
        ];
    }
}
