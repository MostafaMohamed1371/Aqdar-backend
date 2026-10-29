<?php

namespace App\Modules\Notification\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Notification\Http\Resources\InboxNotificationResource;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends BaseController
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $rows = $this->notifications->forUser($user, [
            'category' => (string) $request->query('category', 'all'),
            'unread' => $request->boolean('unread'),
        ]);

        return InboxNotificationResource::collection($rows)->additional([
            'meta' => [
                'unread_count' => $this->notifications->unreadCount($user),
                'smart_enabled' => $this->notifications->smartEnabled(),
            ],
        ]);
    }

    public function read(Request $request, InboxNotification $notification): InboxNotificationResource
    {
        /** @var User $user */
        $user = $request->user();

        return new InboxNotificationResource($this->notifications->markRead($user, $notification));
    }

    public function readAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $count = $this->notifications->markAllRead($user);

        return response()->json([
            'data' => [
                'updated' => $count,
                'unread_count' => 0,
            ],
        ]);
    }
}
