<?php

namespace App\Modules\Listing\Http\Resources;

use App\Modules\Listing\Models\ListingMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ListingMedia */
class ListingMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $request->user() && $this->relationLoaded('listing') === false
            ? true
            : ($request->user()?->id === $this->listing?->user_id);

        return [
            'id' => $this->id,
            'sort_order' => $this->sort_order,
            'caption' => $this->caption,
            'mime' => $this->mime,
            'width' => $this->width,
            'height' => $this->height,
            'moderation_status' => $this->moderation_status?->value,
            'hold_reason' => $this->when($owner, $this->hold_reason),
            'url' => $this->publicUrl(),
        ];
    }
}
