<?php

namespace App\Modules\Notification\Models;

use App\Core\Enums\NotificationCategory;
use App\Modules\Notification\Database\Factories\InboxNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'notifiable_type',
    'notifiable_id',
    'event_key',
    'template_key',
    'category',
    'idempotency_key',
    'title_ar',
    'title_en',
    'body_ar',
    'body_en',
    'data',
    'read_at',
])]
class InboxNotification extends Model
{
    /** @use HasFactory<InboxNotificationFactory> */
    use HasFactory;

    protected $table = 'notifications';

    protected static function newFactory(): InboxNotificationFactory
    {
        return InboxNotificationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'notification_id');
    }

    public function localizedTitle(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' ? $this->title_en : $this->title_ar;
    }

    public function localizedBody(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'en' ? $this->body_en : $this->body_ar;
    }
}
