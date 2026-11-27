<?php

namespace App\Modules\Listing\Http\Resources;

use App\Modules\Listing\Models\ListingMedia;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ListingMedia */
class ListingMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User|null $viewer */
        $viewer = $request->user();
        $this->resource->loadMissing('listing');
        $owner = $viewer instanceof User && (int) $this->listing?->user_id === (int) $viewer->id;

        return [
            'id' => $this->id,
            'sort_order' => $this->sort_order,
            'caption' => $this->caption,
            'kind' => $this->isVideo() ? 'video' : 'photo',
            'mime' => $this->mime,
            'width' => $this->width,
            'height' => $this->height,
            'moderation_status' => $this->moderation_status?->value,
            'hold_reason' => $this->when($owner, $this->hold_reason),
            // Sellers get a signed preview for pending uploads; public clients only get approved URLs.
            'url' => $this->displayUrl($viewer instanceof User ? $viewer : null),
        ];
    }
}
