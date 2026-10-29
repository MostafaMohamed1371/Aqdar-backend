<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\SupportAuthorType;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'thread_id',
    'author_type',
    'user_id',
    'staff_id',
    'body',
])]
class SupportMessage extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'author_type' => SupportAuthorType::class,
            'created_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(SupportThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'staff_id');
    }
}
