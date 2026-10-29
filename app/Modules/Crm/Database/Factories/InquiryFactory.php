<?php

namespace App\Modules\Crm\Database\Factories;

use App\Core\Enums\ContactMethod;
use App\Core\Enums\InquiryStatus;
use App\Core\Enums\PreferredTime;
use App\Modules\Crm\Models\Inquiry;
use App\Modules\Crm\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Inquiry> */
class InquiryFactory extends Factory
{
    protected $model = Inquiry::class;

    public function definition(): array
    {
        $lead = Lead::factory()->create();

        return [
            'public_code' => 'INQ-'.fake()->unique()->numerify('2026-#####'),
            'user_id' => $lead->buyer_id,
            'listing_id' => $lead->listing_id,
            'lead_id' => $lead->id,
            'message' => null,
            'inquiry_type' => 'interested',
            'contact_method' => ContactMethod::Phone,
            'preferred_time' => PreferredTime::Evening,
            'consent_at' => now(),
            'status' => InquiryStatus::New,
        ];
    }
}
