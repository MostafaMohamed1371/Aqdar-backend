<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Dashboard\Http\Requests\ApproveBroadcastRequest;
use App\Modules\Dashboard\Http\Requests\ScheduleBroadcastRequest;
use App\Modules\Dashboard\Http\Requests\UpdateNotificationTemplateRequest;
use App\Modules\Notification\Models\NotificationBroadcast;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Services\Push\FcmConfiguration;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\RedirectResponse;

class NotificationDeskController extends BaseController
{
    public function __construct(
        private NotificationService $notifications,
        private FcmConfiguration $fcm,
    ) {}

    public function updateTemplate(UpdateNotificationTemplateRequest $request, NotificationTemplate $template): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->notifications->updateTemplate($template, $request->validated(), $staff);

        return redirect()
            ->route('dashboard.content.index', ['tab' => 'templates'])
            ->with('status', __('aqdar.notifications.template_saved'));
    }

    public function testSend(ScheduleBroadcastRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage_catalog'), 403);

        /** @var StaffUser $staff */
        $staff = $request->user();
        $count = $this->notifications->testSendToStaff($staff, $request->validated());

        return redirect()
            ->route('dashboard.content.index', ['tab' => 'push'])
            ->with('status', __('aqdar.notifications.test_sent', ['count' => $count]));
    }

    public function verifyFcm(): RedirectResponse
    {
        abort_unless(request()->user()?->can('manage_catalog'), 403);

        $probe = $this->fcm->verifyAuth();

        return redirect()
            ->route('dashboard.content.index', ['tab' => 'push'])
            ->with($probe['ok'] ? 'status' : 'error', $probe['message']);
    }

    public function diagnosticPush(ScheduleBroadcastRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage_catalog'), 403);

        /** @var StaffUser $staff */
        $staff = $request->user();
        $result = $this->notifications->diagnosticDevicePush($staff, $request->validated());

        return redirect()
            ->route('dashboard.content.index', ['tab' => 'push'])
            ->with(
                $result['ok'] ? 'status' : 'error',
                $result['ok']
                    ? __('aqdar.notifications.fcm_diagnostic_ok', ['user' => 'USR-'.$result['user_id']])
                    : __('aqdar.notifications.fcm_diagnostic_failed', ['response' => $result['response']]),
            );
    }

    public function broadcast(ScheduleBroadcastRequest $request): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->notifications->scheduleBroadcast($staff, $request->validated());

        return redirect()
            ->route('dashboard.content.index', ['tab' => 'schedule'])
            ->with('status', __('aqdar.notifications.broadcast_saved'));
    }

    public function approveBroadcast(ApproveBroadcastRequest $request, NotificationBroadcast $broadcast): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->notifications->approveBroadcast($broadcast, $staff);

        return redirect()
            ->route('dashboard.content.index', ['tab' => 'schedule'])
            ->with('status', __('aqdar.notifications.broadcast_approved'));
    }
}
