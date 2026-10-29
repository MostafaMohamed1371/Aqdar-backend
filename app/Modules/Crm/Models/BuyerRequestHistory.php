<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\BuyerRequestStatus;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'buyer_request_id',
    'actor_id',
    'old_status',
    'new_status',
    'reason',
])]
class BuyerRequestHistory extends Model
{
    protected $table = 'buyer_request_history';

    protected function casts(): array
    {
        return [
            'old_status' => BuyerRequestStatus::class,
            'new_status' => BuyerRequestStatus::class,
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BuyerRequest::class, 'buyer_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'actor_id');
    }
}
