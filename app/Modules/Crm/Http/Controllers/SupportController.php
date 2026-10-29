<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Crm\Http\Requests\StoreSupportMessageRequest;
use App\Modules\Crm\Http\Resources\SupportThreadResource;
use App\Modules\Crm\Models\SupportThread;
use App\Modules\Crm\Services\SupportService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends BaseController
{
    public function __construct(private SupportService $support) {}

    public function showCurrent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $thread = $this->support->currentForUser($user);
        if ($thread === null) {
            return response()->json(['data' => null]);
        }

        return (new SupportThreadResource($thread->load('messages')))->response();
    }

    public function store(StoreSupportMessageRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $thread = $this->support->postFromUser($user, $request->validated());

        return (new SupportThreadResource($thread))
            ->response()
            ->setStatusCode($thread->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, SupportThread $supportThread): SupportThreadResource
    {
        /** @var User $user */
        $user = $request->user();

        return new SupportThreadResource($this->support->showForUser($user, $supportThread));
    }
}
