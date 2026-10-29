<?php

namespace App\Modules\Finance\Models;

use App\Core\Enums\CommissionStatus;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'commission_id',
    'actor_id',
    'old_status',
    'new_status',
    'reason',
    'before',
    'after',
])]
class CommissionHistory extends Model
{
    protected $table = 'commission_history';

    protected function casts(): array
    {
        return [
            'old_status' => CommissionStatus::class,
            'new_status' => CommissionStatus::class,
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'actor_id');
    }
}
