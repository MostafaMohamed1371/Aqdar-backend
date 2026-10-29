<?php

namespace App\Modules\User\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\User\Http\Requests\StoreUserRequest;
use App\Modules\User\Http\Requests\UpdateUserRequest;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends BaseController
{
    public function __construct(private UserService $userService) {}

    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection($this->userService->list());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->register($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): UserResource
    {
        $user = $this->userService->find($id);

        abort_if($user === null, 404);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, int $id): UserResource
    {
        return new UserResource($this->userService->update($id, $request->validated()));
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->userService->delete($id);

        abort_if(! $deleted, 404);

        return response()->json(null, 204);
    }
}
