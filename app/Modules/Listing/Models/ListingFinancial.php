<?php

namespace App\Modules\Listing\Models;

use App\Core\Enums\PaymentSystem;
use App\Modules\Listing\Database\Factories\ListingFinancialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'listing_id',
    'declared_contract_price',
    'declared_contract_date',
    'verified_contract_price',
    'contract_price_verified',
    'declared_total_paid',
    'verified_total_paid',
    'total_paid_verified',
    'declared_remaining',
    'verified_remaining',
    'remaining_verified',
    'declared_overdue_count',
    'verified_overdue_count',
    'overdue_count_verified',
    'declared_overdue_amount',
    'verified_overdue_amount',
    'overdue_amount_verified',
    'declared_next_due_date',
    'verified_next_due_date',
    'next_due_date_verified',
    'declared_installment_amount',
    'verified_installment_amount',
    'installment_amount_verified',
    'declared_remaining_installments',
    'declared_payment_system',
    'verified_remaining_installments',
    'remaining_installments_verified',
    'declared_seller_recovery_amount',
    'verified_seller_recovery_amount',
    'seller_recovery_verified',
    'declared_asking_price',
    'verified_asking_price',
    'asking_price_verified',
    'declared_cancellation_percent',
    'declared_assignment_restrictions',
    'verified_cancellation_percent',
    'cancellation_percent_verified',
])]
class ListingFinancial extends Model
{
    /** @use HasFactory<ListingFinancialFactory> */
    use HasFactory;

    /** Buyer-facing values copied to public_* columns so search can filter and sort on them. */
    public const SEARCHABLE_PUBLIC_FIELDS = ['asking_price', 'remaining', 'installment_amount'];

    protected static function booted(): void
    {
        static::saving(function (ListingFinancial $financial): void {
            foreach (self::SEARCHABLE_PUBLIC_FIELDS as $field) {
                $financial->setAttribute('public_'.$field, $financial->publicValue($field));
            }
        });
    }

    protected static function newFactory(): ListingFinancialFactory
    {
        return ListingFinancialFactory::new();
    }

    protected function casts(): array
    {
        return [
            'declared_contract_price' => 'decimal:2',
            'declared_contract_date' => 'date',
            'verified_contract_price' => 'decimal:2',
            'contract_price_verified' => 'boolean',
            'declared_total_paid' => 'decimal:2',
            'verified_total_paid' => 'decimal:2',
            'total_paid_verified' => 'boolean',
            'declared_remaining' => 'decimal:2',
            'verified_remaining' => 'decimal:2',
            'remaining_verified' => 'boolean',
            'declared_overdue_count' => 'integer',
            'verified_overdue_count' => 'integer',
            'overdue_count_verified' => 'boolean',
            'declared_overdue_amount' => 'decimal:2',
            'verified_overdue_amount' => 'decimal:2',
            'overdue_amount_verified' => 'boolean',
            'declared_next_due_date' => 'date',
            'verified_next_due_date' => 'date',
            'next_due_date_verified' => 'boolean',
            'declared_installment_amount' => 'decimal:2',
            'verified_installment_amount' => 'decimal:2',
            'installment_amount_verified' => 'boolean',
            'declared_remaining_installments' => 'integer',
            'declared_payment_system' => PaymentSystem::class,
            'verified_remaining_installments' => 'integer',
            'remaining_installments_verified' => 'boolean',
            'declared_seller_recovery_amount' => 'decimal:2',
            'verified_seller_recovery_amount' => 'decimal:2',
            'seller_recovery_verified' => 'boolean',
            'declared_asking_price' => 'decimal:2',
            'verified_asking_price' => 'decimal:2',
            'asking_price_verified' => 'boolean',
            'public_asking_price' => 'decimal:2',
            'public_remaining' => 'decimal:2',
            'public_installment_amount' => 'decimal:2',
            'declared_cancellation_percent' => 'decimal:2',
            'verified_cancellation_percent' => 'decimal:2',
            'cancellation_percent_verified' => 'boolean',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function publicValue(string $field): mixed
    {
        $verifiedFlag = $field.'_verified';
        $verified = 'verified_'.$field;
        $declared = 'declared_'.$field;

        if ($this->{$verifiedFlag} && $this->{$verified} !== null) {
            return $this->{$verified};
        }

        return $this->{$declared};
    }
}
