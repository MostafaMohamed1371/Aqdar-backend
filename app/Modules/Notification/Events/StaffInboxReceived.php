<?php

namespace App\Modules\Notification\Events;

use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffInboxReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public StaffUser $staff,
        public InboxNotification $notification,
        public int $unreadCount,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('staff.'.$this->staff->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'staff.inbox.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'unread_count' => $this->unreadCount,
            'notification' => [
                'id' => $this->notification->id,
                'event_key' => $this->notification->event_key,
                'title' => $this->notification->localizedTitle(),
                'body' => $this->notification->localizedBody(),
                'created_at' => optional($this->notification->created_at)?->toIso8601String(),
            ],
        ];
    }
}
