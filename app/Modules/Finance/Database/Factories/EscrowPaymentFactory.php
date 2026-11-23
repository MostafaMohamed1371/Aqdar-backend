<?php

namespace App\Modules\Finance\Database\Factories;

use App\Core\Enums\EscrowMethod;
use App\Core\Enums\EscrowStatus;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Finance\Models\EscrowPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EscrowPayment> */
class EscrowPaymentFactory extends Factory
{
    protected $model = EscrowPayment::class;

    public function definition(): array
    {
        return [
            'case_id' => TransferCase::factory(),
            'amount' => 150000,
            'method' => EscrowMethod::BankTransfer,
            'reference' => 'TRF-'.fake()->numerify('######'),
            'status' => EscrowStatus::Held,
            'received_at' => now(),
            'settled_at' => null,
            'note' => null,
        ];
    }
}
