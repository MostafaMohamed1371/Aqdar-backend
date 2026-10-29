<?php

namespace App\Modules\Notification\Http\Resources;

use App\Modules\Notification\Models\InboxNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InboxNotification */
class InboxNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event_key,
            'category' => $this->category?->value,
            'title' => $this->localizedTitle(),
            'body' => $this->localizedBody(),
            'context' => $this->data ?? [],
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
