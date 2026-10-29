<?php

namespace App\Modules\Crm\Database\Factories;

use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\RequestMatch;
use App\Modules\Listing\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RequestMatch> */
class RequestMatchFactory extends Factory
{
    protected $model = RequestMatch::class;

    public function definition(): array
    {
        return [
            'buyer_request_id' => BuyerRequest::factory(),
            'listing_id' => Listing::factory()->published(),
            'score' => 80,
        ];
    }
}
