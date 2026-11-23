<?php

namespace App\Modules\Crm\Models;

use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'case_id',
    'item_key',
    'done_at',
    'done_by_staff_id',
])]
class CaseChecklistItem extends Model
{
    protected function casts(): array
    {
        return [
            'done_at' => 'datetime',
        ];
    }

    public function transferCase(): BelongsTo
    {
        return $this->belongsTo(TransferCase::class, 'case_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'done_by_staff_id');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }
}
