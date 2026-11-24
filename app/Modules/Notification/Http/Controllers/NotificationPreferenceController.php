<?php

namespace App\Modules\Notification\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Notification\Http\Requests\UpdateNotificationPreferenceRequest;
use App\Modules\Notification\Models\NotificationPreference;
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

        return response()->json(['data' => $this->payload($prefs, $user)]);
    }

    public function update(UpdateNotificationPreferenceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (array_key_exists('locale', $data) && is_string($data['locale'])) {
            $user->forceFill(['locale' => $data['locale']])->save();
            unset($data['locale']);
        }

        $prefs = $data === []
            ? $this->notifications->preferences($user)
            : $this->notifications->updatePreferences($user, $data);

        return response()->json(['data' => $this->payload($prefs, $user->fresh() ?? $user)]);
    }

    private function payload(NotificationPreference $prefs, User $user): array
    {
        return [
            'locale' => $user->locale,
            'push_enabled' => (bool) $prefs->push_enabled,
            'email_enabled' => (bool) $prefs->email_enabled,
            'sms_enabled' => (bool) $prefs->sms_enabled,
            'marketing_opt_in' => (bool) $prefs->marketing_opt_in,
            'in_app_always_on' => true,
        ];
    }
}
