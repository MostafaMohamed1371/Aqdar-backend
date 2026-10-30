<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Crm\Services\InquiryService;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerInterestController extends BaseController
{
    public function __construct(private InquiryService $inquiries) {}

    public function all(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $interest = $this->inquiries->interestForSeller($user);

        return response()->json([
            'data' => $interest['rows'],
            'counts' => $interest['counts'],
            'sales_contact' => $this->inquiries->salesContact(),
        ]);
    }

    public function index(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->user_id === $request->user()->id, 404);

        /** @var User $user */
        $user = $request->user();
        $interest = $this->inquiries->interestForListing($user, $listing);

        return response()->json([
            'data' => $interest['rows'],
            'counts' => $interest['counts'],
            'sales_contact' => $this->inquiries->salesContact(),
        ]);
    }
}
