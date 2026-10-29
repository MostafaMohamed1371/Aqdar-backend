<?php

namespace App\Modules\Listing\Http\Resources;

use App\Modules\Listing\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class MapPinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $decimals = (int) config('aqdar.map.precision', 3);

        return [
            'id' => $this->id,
            'public_code' => $this->public_code,
            'lat' => $this->lat !== null ? round((float) $this->lat, $decimals) : null,
            'lng' => $this->lng !== null ? round((float) $this->lng, $decimals) : null,
            'asking_price' => $this->publicAskingPrice() !== null ? (float) $this->publicAskingPrice() : null,
            'segment' => $this->segment?->value,
        ];
    }
}
