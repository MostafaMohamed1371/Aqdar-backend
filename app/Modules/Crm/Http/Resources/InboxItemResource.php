<?php

namespace App\Modules\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InboxItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'id' => $row['id'],
            'public_code' => $row['public_code'],
            'kind' => $row['kind'],
            'status' => $row['status'],
            'label' => $row['label'] ?? $row['status'],
            'listing' => $row['listing'],
            'created_at' => $row['created_at'],
        ];
    }
}
