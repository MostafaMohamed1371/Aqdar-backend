<?php

namespace App\Modules\Listing\Http\Resources;

use App\Modules\Listing\Models\ListingStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ListingStatusHistory */
class ListingHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status?->value,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
