<?php

namespace App\Modules\Finance\Models;

use App\Core\Enums\CommissionBasis;
use App\Core\Enums\CommissionPayer;
use App\Core\Enums\CommissionStatus;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Finance\Database\Factories\CommissionFactory;
use App\Modules\Listing\Models\Listing;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'case_id',
    'listing_id',
    'payer_party',
    'basis',
    'rate',
    'sale_value',
    'amount',
    'tax_amount',
    'invoice_reference',
    'status',
    'note',
    'created_by',
    'approved_at',
    'approved_by',
])]
class Commission extends Model
{
    /** @use HasFactory<CommissionFactory> */
    use HasFactory;

    protected static function newFactory(): CommissionFactory
    {
        return CommissionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'payer_party' => CommissionPayer::class,
            'basis' => CommissionBasis::class,
            'status' => CommissionStatus::class,
            'rate' => 'decimal:3',
            'sale_value' => 'decimal:2',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function transferCase(): BelongsTo
    {
        return $this->belongsTo(TransferCase::class, 'case_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function history(): HasMany
    {
        return $this->hasMany(CommissionHistory::class)->latest('id');
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'status' => $this->status?->value,
            'payer_party' => $this->payer_party?->value,
            'basis' => $this->basis?->value,
            'rate' => $this->rate !== null ? (float) $this->rate : null,
            'sale_value' => $this->sale_value !== null ? (float) $this->sale_value : null,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'invoice_reference' => $this->invoice_reference,
        ];
    }
}
