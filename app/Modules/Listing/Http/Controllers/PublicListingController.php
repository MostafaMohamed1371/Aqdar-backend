<?php

namespace App\Modules\Listing\Http\Controllers;

use App\Core\Enums\MediaModerationStatus;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Listing\Http\Requests\ListingSearchRequest;
use App\Modules\Listing\Http\Resources\MapPinResource;
use App\Modules\Listing\Http\Resources\PublicListingResource;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Services\ListingSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicListingController extends BaseController
{
    public function __construct(private ListingSearchService $search) {}

    public function index(ListingSearchRequest $request): AnonymousResourceCollection
    {
        return PublicListingResource::collection(
            $this->search->search($request->validated()),
        );
    }

    public function counts(): JsonResponse
    {
        return response()->json(['data' => $this->search->counts()]);
    }

    public function show(Listing $listing): PublicListingResource
    {
        abort_unless($listing->isPubliclyVisible(), 404);

        Listing::query()->whereKey($listing->id)->increment('view_count');
        $listing->view_count = (int) $listing->view_count + 1;
        $listing->load(['financials', 'governorate', 'area', 'developer', 'project', 'unitType', 'category', 'media']);

        return new PublicListingResource($listing);
    }

    public function media(Listing $listing): JsonResponse
    {
        abort_unless($listing->isPubliclyVisible(), 404);

        $photos = $listing->media()
            ->where('moderation_status', MediaModerationStatus::Approved)
            ->whereNotNull('public_path')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($media) => [
                'id' => $media->id,
                'sort_order' => $media->sort_order,
                'url' => $media->publicUrl(),
                'mime' => $media->mime,
                'width' => $media->width,
                'height' => $media->height,
            ])
            ->values();

        return response()->json(['data' => $photos]);
    }

    public function map(ListingSearchRequest $request): AnonymousResourceCollection
    {
        return MapPinResource::collection(
            $this->search->mapPins($request->validated()),
        );
    }
}
