<?php

namespace App\Modules\Listing\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Services\EstimateService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstimateController extends BaseController
{
    public function __construct(private EstimateService $estimates) {}

    public function show(Request $request, Listing $listing): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->estimates->forListing($listing, $user),
        ]);
    }
}
