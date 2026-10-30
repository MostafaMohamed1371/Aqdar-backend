<?php

namespace App\Modules\Listing\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Listing\Http\Resources\PublicListingResource;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecentlyViewedController extends BaseController
{
    public const LIMIT = 30;

    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;

        $listings = Listing::query()
            ->public()
            ->whereHas('views', fn ($query) => $query->where('user_id', $userId))
            ->with(['financials', 'governorate', 'area', 'developer', 'project', 'unitType', 'category'])
            ->orderByDesc(ListingView::query()
                ->select('viewed_at')
                ->whereColumn('listing_views.listing_id', 'listings.id')
                ->where('user_id', $userId)
                ->limit(1))
            ->limit(self::LIMIT)
            ->get();

        return PublicListingResource::collection($listings);
    }

    public function destroy(Request $request): JsonResponse
    {
        ListingView::query()->where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => __('aqdar.listings.recently_viewed_cleared')]);
    }
}
