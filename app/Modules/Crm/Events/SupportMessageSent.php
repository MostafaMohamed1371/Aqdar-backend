<?php

namespace App\Modules\Crm\Events;

use App\Core\Enums\SupportAuthorType;
use App\Modules\Crm\Models\SupportMessage;
use App\Modules\Crm\Models\SupportThread;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public SupportThread $thread,
        public SupportMessage $message,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.thread.'.$this->thread->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->thread->id,
            'public_code' => $this->thread->public_code,
            'status' => $this->thread->status?->value,
            'message' => [
                'id' => $this->message->id,
                'author' => $this->message->author_type === SupportAuthorType::Staff ? 'sales' : 'user',
                'body' => $this->message->body,
                'created_at' => optional($this->message->created_at)?->toIso8601String(),
            ],
        ];
    }
}
