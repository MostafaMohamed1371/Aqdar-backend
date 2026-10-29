<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Enums\ContentBlockType;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Catalog\Http\Resources\ContentBlockResource;
use App\Modules\Catalog\Http\Resources\NamedCatalogResource;
use App\Modules\Catalog\Services\CatalogReadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends BaseController
{
    public function __construct(private CatalogReadService $catalog) {}

    public function locations(): AnonymousResourceCollection
    {
        return NamedCatalogResource::collection($this->catalog->locations());
    }

    public function developers(): AnonymousResourceCollection
    {
        return NamedCatalogResource::collection($this->catalog->developers());
    }

    public function projects(Request $request): AnonymousResourceCollection
    {
        $developerId = $request->integer('developer_id') ?: null;

        return NamedCatalogResource::collection($this->catalog->projects($developerId));
    }

    public function unitTypes(): AnonymousResourceCollection
    {
        return NamedCatalogResource::collection($this->catalog->unitTypes());
    }

    public function amenities(): AnonymousResourceCollection
    {
        return NamedCatalogResource::collection($this->catalog->amenities());
    }

    public function tags(): AnonymousResourceCollection
    {
        return NamedCatalogResource::collection($this->catalog->tags());
    }

    public function banners(): AnonymousResourceCollection
    {
        return ContentBlockResource::collection($this->catalog->content(ContentBlockType::Banner));
    }

    public function faqs(): AnonymousResourceCollection
    {
        return ContentBlockResource::collection($this->catalog->content(ContentBlockType::Faq));
    }

    public function support(): AnonymousResourceCollection
    {
        return ContentBlockResource::collection($this->catalog->content(ContentBlockType::Support));
    }
}
