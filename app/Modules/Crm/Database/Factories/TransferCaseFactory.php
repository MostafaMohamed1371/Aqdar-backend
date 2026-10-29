<?php

namespace App\Modules\Crm\Database\Factories;

use App\Core\Enums\CaseStage;
use App\Core\Enums\CaseStatus;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TransferCase> */
class TransferCaseFactory extends Factory
{
    protected $model = TransferCase::class;

    public function definition(): array
    {
        $listing = Listing::factory()->published()->create();

        return [
            'public_code' => 'TR-'.fake()->unique()->numerify('2026-#####'),
            'buyer_id' => User::factory(),
            'seller_id' => $listing->user_id,
            'listing_id' => $listing->id,
            'internal_stage' => CaseStage::NewClient,
            'client_label' => CaseStage::NewClient->clientLabel(),
            'status' => CaseStatus::Open,
            'missing_documents_count' => 0,
            'last_transition_at' => now(),
        ];
    }
}
