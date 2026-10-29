<?php

namespace App\Modules\Notification\Models;

use App\Core\Enums\NotificationCategory;
use App\Modules\Notification\Database\Factories\NotificationTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'category',
    'title_ar',
    'title_en',
    'body_ar',
    'body_en',
    'is_operational',
    'is_active',
])]
class NotificationTemplate extends Model
{
    /** @use HasFactory<NotificationTemplateFactory> */
    use HasFactory;

    protected static function newFactory(): NotificationTemplateFactory
    {
        return NotificationTemplateFactory::new();
    }

    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'is_operational' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
