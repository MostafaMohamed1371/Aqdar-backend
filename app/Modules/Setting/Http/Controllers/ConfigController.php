<?php

namespace App\Modules\Setting\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Setting\Services\SettingService;
use Illuminate\Http\JsonResponse;

class ConfigController extends BaseController
{
    public function __construct(private SettingService $settingService) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->settingService->publicConfig(),
        ]);
    }

    public function salesContact(): JsonResponse
    {
        return response()->json([
            'data' => $this->settingService->publicConfig()['sales_contact'],
        ]);
    }
}
