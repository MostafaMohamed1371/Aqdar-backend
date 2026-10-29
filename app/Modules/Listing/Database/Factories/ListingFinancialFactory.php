<?php

namespace App\Modules\Listing\Database\Factories;

use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingFinancial;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ListingFinancial> */
class ListingFinancialFactory extends Factory
{
    protected $model = ListingFinancial::class;

    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'declared_contract_price' => 2000000,
            'declared_total_paid' => 800000,
            'declared_remaining' => 1200000,
            'declared_overdue_count' => 0,
            'declared_overdue_amount' => 0,
            'declared_installment_amount' => 15000,
            'declared_remaining_installments' => 80,
            'declared_asking_price' => 1850000,
        ];
    }
}
