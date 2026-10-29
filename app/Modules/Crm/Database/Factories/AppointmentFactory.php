<?php

namespace App\Modules\Crm\Database\Factories;

use App\Core\Enums\AppointmentStatus;
use App\Modules\Crm\Models\Appointment;
use App\Modules\Crm\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $lead = Lead::factory()->create();

        return [
            'public_code' => 'APT-'.fake()->unique()->numerify('2026-#####'),
            'user_id' => $lead->buyer_id,
            'listing_id' => $lead->listing_id,
            'lead_id' => $lead->id,
            'requested_date' => now()->addDays(3)->toDateString(),
            'requested_slot' => '10:30',
            'notes' => null,
            'status' => AppointmentStatus::Requested,
            'consent_at' => now(),
        ];
    }
}
