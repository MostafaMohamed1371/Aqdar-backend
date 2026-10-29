<?php

namespace App\Modules\Setting\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Setting\Http\Requests\StoreSettingRequest;
use App\Modules\Setting\Http\Requests\UpdateSettingRequest;
use App\Modules\Setting\Http\Resources\SettingResource;
use App\Modules\Setting\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettingController extends BaseController
{
    public function __construct(private SettingService $settingService) {}

    public function index(): AnonymousResourceCollection
    {
        return SettingResource::collection($this->settingService->all());
    }

    public function showByKey(string $key): SettingResource
    {
        $setting = $this->settingService->findByKey($key);

        abort_if($setting === null, 404);

        return new SettingResource($setting);
    }

    public function store(StoreSettingRequest $request): JsonResponse
    {
        $setting = $this->settingService->create($request->validated());

        return (new SettingResource($setting))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSettingRequest $request, int $setting): SettingResource
    {
        return new SettingResource($this->settingService->update($setting, $request->validated()));
    }

    public function destroy(int $setting): JsonResponse
    {
        $deleted = $this->settingService->delete($setting);

        abort_if(! $deleted, 404);

        return response()->json(null, 204);
    }
}
