<?php

namespace App\Modules\Listing\Http\Resources;

use App\Modules\Listing\Models\ListingFormField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ListingFormField */
class ListingFormFieldResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->field_key,
            'label_ar' => $this->label_ar,
            'label_en' => $this->label_en ?? $this->label_ar,
            'type' => $this->type?->value,
            'options' => $this->options ?? [],
            'is_required' => (bool) $this->is_required,
        ];
    }
}
