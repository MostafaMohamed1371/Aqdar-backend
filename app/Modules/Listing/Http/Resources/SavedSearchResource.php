<?php

namespace App\Modules\Listing\Http\Resources;

use App\Modules\Listing\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SavedSearch */
class SavedSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'filters' => $this->filters ?? [],
            'notify' => $this->notify,
            'last_matched_at' => $this->last_matched_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
