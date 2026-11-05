<?php

namespace App\Modules\Listing\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Listing\Http\Resources\PublicListingResource;
use App\Modules\Listing\Models\Favorite;
use App\Modules\Listing\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class FavoriteController extends BaseController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $listings = Listing::query()
            ->public()
            ->whereHas('favorites', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['financials', 'governorate', 'area', 'developer', 'project', 'unitType', 'category', 'media'])
            ->latest('published_at')
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 50));

        return PublicListingResource::collection($listings);
    }

    public function store(Request $request, Listing $listing): JsonResponse
    {
        if (! $listing->isPubliclyVisible()) {
            throw ValidationException::withMessages([
                'listing' => [__('aqdar.listings.not_favoriteable')],
            ]);
        }

        Favorite::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'listing_id' => $listing->id,
        ]);

        $listing->load(['financials', 'governorate', 'area', 'developer', 'project', 'unitType', 'category', 'media']);

        return (new PublicListingResource($listing))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Listing $listing): JsonResponse
    {
        Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('listing_id', $listing->id)
            ->delete();

        return response()->json(['message' => __('aqdar.listings.favorite_removed')]);
    }
}
