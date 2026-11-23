<?php

namespace App\Modules\Crm\Events;

use App\Core\Enums\SupportAuthorType;
use App\Modules\Crm\Models\SupportMessage;
use App\Modules\Crm\Models\SupportThread;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportDeskUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public SupportThread $thread,
        public ?SupportMessage $message = null,
        public string $action = 'message',
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.desk'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.desk.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'thread' => [
                'id' => $this->thread->id,
                'public_code' => $this->thread->public_code,
                'user_id' => $this->thread->user_id,
                'assigned_to' => $this->thread->assigned_to,
                'status' => $this->thread->status?->value,
            ],
            'message' => $this->message === null ? null : [
                'id' => $this->message->id,
                'author' => $this->message->author_type === SupportAuthorType::Staff ? 'sales' : 'user',
                'body' => $this->message->body,
                'created_at' => optional($this->message->created_at)?->toIso8601String(),
            ],
        ];
    }
}
