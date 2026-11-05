<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Core\Support\PublicMediaUrl;
use App\Modules\Catalog\Models\ContentBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentBlock */
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
            'image_url' => PublicMediaUrl::for($this->image_path),
            'link_url' => $this->link_url,
            'sort_order' => $this->sort_order,
        ];
    }
}
