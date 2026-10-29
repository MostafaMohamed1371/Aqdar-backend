<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\SupportThreadStatus;
use App\Modules\Crm\Database\Factories\SupportThreadFactory;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'public_code',
    'user_id',
    'assigned_to',
    'status',
    'consent_at',
])]
class SupportThread extends Model
{
    /** @use HasFactory<SupportThreadFactory> */
    use HasFactory;

    protected static function newFactory(): SupportThreadFactory
    {
        return SupportThreadFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => SupportThreadStatus::class,
            'consent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'thread_id')->orderBy('id');
    }
}
