<?php

namespace App\Modules\Notification\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Notification\Http\Requests\UpdateNotificationPreferenceRequest;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends BaseController
{
    public function __construct(private NotificationService $notifications) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $prefs = $this->notifications->preferences($user);

        return response()->json(['data' => $this->payload($prefs)]);
    }

    public function update(UpdateNotificationPreferenceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $prefs = $this->notifications->updatePreferences($user, $request->validated());

        return response()->json(['data' => $this->payload($prefs)]);
    }

    private function payload($prefs): array
    {
        return [
            'push_enabled' => (bool) $prefs->push_enabled,
            'email_enabled' => (bool) $prefs->email_enabled,
            'sms_enabled' => (bool) $prefs->sms_enabled,
            'marketing_opt_in' => (bool) $prefs->marketing_opt_in,
            'in_app_always_on' => true,
        ];
    }
}
