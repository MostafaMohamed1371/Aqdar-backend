<?php

namespace App\Modules\Finance\Models;

use App\Core\Enums\EscrowMethod;
use App\Core\Enums\EscrowStatus;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Finance\Database\Factories\EscrowPaymentFactory;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'case_id',
    'amount',
    'method',
    'reference',
    'status',
    'received_at',
    'settled_at',
    'recorded_by_staff_id',
    'settled_by_staff_id',
    'note',
])]
class EscrowPayment extends Model
{
    /** @use HasFactory<EscrowPaymentFactory> */
    use HasFactory;

    protected static function newFactory(): EscrowPaymentFactory
    {
        return EscrowPaymentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => EscrowMethod::class,
            'status' => EscrowStatus::class,
            'received_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function transferCase(): BelongsTo
    {
        return $this->belongsTo(TransferCase::class, 'case_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'recorded_by_staff_id');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'settled_by_staff_id');
    }
}
