<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Crm\Http\Requests\StoreInquiryRequest;
use App\Modules\Crm\Http\Resources\InboxItemResource;
use App\Modules\Crm\Http\Resources\InquiryResource;
use App\Modules\Crm\Models\Inquiry;
use App\Modules\Crm\Services\ActivityFeedService;
use App\Modules\Crm\Services\InquiryService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InquiryController extends BaseController
{
    public function __construct(
        private InquiryService $inquiries,
        private ActivityFeedService $feed,
    ) {}

    public function store(StoreInquiryRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $inquiry = $this->inquiries->create($user, $request->validated());

        return (new InquiryResource($inquiry))
            ->response()
            ->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return InboxItemResource::collection($this->feed->forBuyer($user, [
            'status' => (string) $request->query('status', ''),
        ]));
    }

    public function show(Request $request, Inquiry $inquiry): InquiryResource
    {
        /** @var User $user */
        $user = $request->user();

        return new InquiryResource($this->inquiries->showForBuyer($user, $inquiry));
    }

    public function callback(Request $request, Inquiry $inquiry): InquiryResource
    {
        /** @var User $user */
        $user = $request->user();

        return new InquiryResource($this->inquiries->requestCallback($user, $inquiry));
    }
}
