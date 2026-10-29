<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Crm\Http\Requests\StoreBuyerRequestRequest;
use App\Modules\Crm\Http\Resources\BuyerRequestResource;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Services\BuyerRequestService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuyerRequestController extends BaseController
{
    public function __construct(private BuyerRequestService $requests) {}

    public function store(StoreBuyerRequestRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $created = $this->requests->create($user, $request->validated());

        return (new BuyerRequestResource($created))
            ->response()
            ->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return BuyerRequestResource::collection($this->requests->forBuyer($user));
    }

    public function show(Request $request, BuyerRequest $buyerRequest): BuyerRequestResource
    {
        /** @var User $user */
        $user = $request->user();

        return new BuyerRequestResource($this->requests->showForBuyer($user, $buyerRequest));
    }

    public function callback(Request $request, BuyerRequest $buyerRequest): BuyerRequestResource
    {
        /** @var User $user */
        $user = $request->user();

        return new BuyerRequestResource($this->requests->requestCallback($user, $buyerRequest));
    }
}
