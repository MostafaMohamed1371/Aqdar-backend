<?php

namespace App\Modules\Notification\Models;

use App\Core\Enums\DeliveryStatus;
use App\Core\Enums\NotificationChannel;
use App\Modules\User\Models\UserDevice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'notification_id',
    'channel',
    'status',
    'attempts',
    'device_id',
    'provider_response',
    'skip_reason',
    'sent_at',
])]
class NotificationDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => DeliveryStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(InboxNotification::class, 'notification_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }
}
