<?php

namespace App\Modules\Listing\Http\Controllers;

use App\Core\Enums\MediaModerationStatus;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Listing\Http\Requests\CompareListingsRequest;
use App\Modules\Listing\Http\Requests\ListingSearchRequest;
use App\Modules\Listing\Http\Resources\MapPinResource;
use App\Modules\Listing\Http\Resources\PublicListingResource;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingView;
use App\Modules\Listing\Services\ListingSearchService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function compare(CompareListingsRequest $request): AnonymousResourceCollection
    {
        return PublicListingResource::collection(
            $this->search->compare($request->listingIds()),
        );
    }

    public function show(Request $request, Listing $listing): PublicListingResource
    {
        abort_unless($listing->isPubliclyVisible(), 404);

        Listing::query()->whereKey($listing->id)->increment('view_count');

        $viewer = $request->user();
        if ($viewer instanceof User) {
            ListingView::query()->upsert(
                [['user_id' => $viewer->id, 'listing_id' => $listing->id, 'viewed_at' => now()]],
                ['user_id', 'listing_id'],
                ['viewed_at'],
            );
        }

        $listing->view_count = (int) $listing->view_count + 1;
        $listing->load(['financials', 'installmentItems', 'governorate', 'area', 'developer', 'project', 'unitType', 'category', 'media']);

        return new PublicListingResource($listing);
    }

    public function media(Listing $listing): JsonResponse
    {
        abort_unless($listing->isPubliclyVisible(), 404);

        $photos = $listing->media()
            ->whereNotNull('public_path')
            ->where('public_path', '!=', '')
            ->whereIn('moderation_status', [
                MediaModerationStatus::Pending,
                MediaModerationStatus::Approved,
            ])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($media) => [
                'id' => $media->id,
                'sort_order' => $media->sort_order,
                'kind' => $media->isVideo() ? 'video' : 'photo',
                'url' => $media->storedPublicUrl() ?? $media->publicUrl(),
                'mime' => $media->mime,
                'width' => $media->width,
                'height' => $media->height,
                'moderation_status' => $media->moderation_status?->value,
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
