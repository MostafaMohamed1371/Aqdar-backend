<?php

namespace App\Modules\Listing\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Listing\Http\Resources\PublicListingResource;
use App\Modules\Listing\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicShareController extends BaseController
{
    public function show(Request $request, string $code): View
    {
        $listing = Listing::query()->where('public_code', $code)->first();
        abort_unless($listing !== null && $listing->isPubliclyVisible(), 404);

        Listing::query()->whereKey($listing->id)->increment('view_count');
        $listing->view_count = (int) $listing->view_count + 1;
        $listing->load(['financials', 'governorate', 'area', 'developer', 'project', 'unitType', 'category', 'media']);

        return view('listing::share', [
            'listing' => (new PublicListingResource($listing))->toArray($request),
            'code' => $listing->public_code,
        ]);
    }
}
