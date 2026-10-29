<?php

namespace App\Modules\Crm\Database\Factories;

use App\Core\Enums\BuyerIntent;
use App\Core\Enums\BuyerRequestStatus;
use App\Core\Enums\ContactMethod;
use App\Core\Enums\SegmentPreference;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BuyerRequest> */
class BuyerRequestFactory extends Factory
{
    protected $model = BuyerRequest::class;

    public function definition(): array
    {
        return [
            'public_code' => 'BR-'.fake()->unique()->numerify('2026-#####'),
            'user_id' => User::factory(),
            'status' => BuyerRequestStatus::New,
            'intent' => BuyerIntent::Living,
            'segment_preference' => SegmentPreference::Any,
            'budget_min' => 2000000,
            'budget_max' => 3500000,
            'area_sqm_min' => 100,
            'area_sqm_max' => 160,
            'bedrooms' => 3,
            'contact_method' => ContactMethod::Phone,
            'consent_at' => now(),
            'criteria' => [],
        ];
    }
}
