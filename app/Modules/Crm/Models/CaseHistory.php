<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\CaseStage;
use App\Core\Enums\CaseStatus;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'case_id',
    'actor_id',
    'old_stage',
    'new_stage',
    'old_status',
    'new_status',
    'reason',
    'internal_note',
])]
class CaseHistory extends Model
{
    protected $table = 'case_history';

    protected function casts(): array
    {
        return [
            'old_stage' => CaseStage::class,
            'new_stage' => CaseStage::class,
            'old_status' => CaseStatus::class,
            'new_status' => CaseStatus::class,
        ];
    }

    public function transferCase(): BelongsTo
    {
        return $this->belongsTo(TransferCase::class, 'case_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'actor_id');
    }
}
