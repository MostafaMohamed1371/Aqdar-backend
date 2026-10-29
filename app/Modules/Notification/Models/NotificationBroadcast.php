<?php

namespace App\Modules\Notification\Models;

use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'title_ar',
    'title_en',
    'body_ar',
    'body_en',
    'audience',
    'status',
    'scheduled_at',
    'sent_at',
    'created_by',
])]
class NotificationBroadcast extends Model
{
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'created_by');
    }
}
