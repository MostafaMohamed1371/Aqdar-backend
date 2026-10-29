<?php

namespace App\Modules\Finance\Database\Factories;

use App\Core\Enums\CaseStatus;
use App\Core\Enums\CommissionBasis;
use App\Core\Enums\CommissionPayer;
use App\Core\Enums\CommissionStatus;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Finance\Models\Commission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Commission> */
class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    public function definition(): array
    {
        $case = TransferCase::factory()->create(['status' => CaseStatus::Completed]);

        return [
            'case_id' => $case->id,
            'listing_id' => $case->listing_id,
            'payer_party' => CommissionPayer::Buyer,
            'basis' => CommissionBasis::Percent,
            'rate' => 2.5,
            'sale_value' => 3200000,
            'amount' => 80000,
            'invoice_reference' => null,
            'status' => CommissionStatus::Due,
        ];
    }
}
