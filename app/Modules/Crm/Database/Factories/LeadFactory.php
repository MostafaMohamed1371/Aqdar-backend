<?php

namespace App\Modules\Crm\Database\Factories;

use App\Core\Enums\LeadPriority;
use App\Core\Enums\LeadSource;
use App\Core\Enums\LeadStatus;
use App\Modules\Crm\Models\Lead;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lead> */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'public_code' => 'LD-'.fake()->unique()->numerify('2026-#####'),
            'source' => LeadSource::ListingInquiry,
            'buyer_id' => User::factory(),
            'listing_id' => Listing::factory()->published(),
            'seller_id' => function (array $attributes) {
                return Listing::query()->find($attributes['listing_id'])?->user_id;
            },
            'status' => LeadStatus::New,
            'priority' => LeadPriority::Medium,
            'next_action' => 'first_call',
            'sla_due_at' => now()->addHours(2),
            'is_duplicate' => false,
        ];
    }
}
