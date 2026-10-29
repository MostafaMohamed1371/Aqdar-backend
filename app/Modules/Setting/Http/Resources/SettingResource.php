<?php

namespace App\Modules\Setting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Setting\Models\Setting */
class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'value' => $this->value,
            'group' => $this->group,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
