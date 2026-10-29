<?php

namespace App\Modules\Listing\Http\Resources;

use App\Modules\Listing\Models\ListingDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ListingDocument */
class ListingDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listing_id' => $this->listing_id,
            'public_code' => $this->whenLoaded('listing', fn () => $this->listing?->public_code),
            'type' => $this->type?->value,
            'version' => $this->version,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes,
            'review_status' => $this->review_status?->value,
            'review_note' => $this->review_note,
            'created_at' => $this->created_at,
        ];
    }
}
