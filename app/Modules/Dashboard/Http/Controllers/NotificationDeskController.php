<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Dashboard\Http\Requests\ScheduleBroadcastRequest;
use App\Modules\Dashboard\Http\Requests\UpdateNotificationTemplateRequest;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\RedirectResponse;

class NotificationDeskController extends BaseController
{
    public function __construct(private NotificationService $notifications) {}

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
        /** @var StaffUser $staff */
        $staff = $request->user();
        $count = $this->notifications->testSendToStaff($staff, $request->validated());

        return redirect()
            ->route('dashboard.content.index', ['tab' => 'push'])
            ->with('status', __('aqdar.notifications.test_sent', ['count' => $count]));
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
}
