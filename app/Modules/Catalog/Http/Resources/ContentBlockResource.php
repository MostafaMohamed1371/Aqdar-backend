<?php

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Modules\Catalog\Models\ContentBlock */
class ContentBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'key' => $this->key,
            'title' => $this->localizedTitle(),
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
            'body' => $this->localizedBody(),
            'body_ar' => $this->body_ar,
            'body_en' => $this->body_en,
            'image_path' => $this->image_path,
            'image_url' => $this->image_path
                ? Storage::disk('public_media')->url($this->image_path)
                : null,
            'link_url' => $this->link_url,
            'sort_order' => $this->sort_order,
        ];
    }
}
