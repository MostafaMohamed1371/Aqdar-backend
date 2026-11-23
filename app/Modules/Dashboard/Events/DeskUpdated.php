<?php

namespace App\Modules\Dashboard\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeskUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<string>  $screens
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $resource,
        public string $action,
        public ?int $id = null,
        public array $screens = [],
        public array $meta = [],
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('desk'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'desk.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'resource' => $this->resource,
            'action' => $this->action,
            'id' => $this->id,
            'screens' => $this->screens,
            'meta' => $this->meta,
        ];
    }
}
