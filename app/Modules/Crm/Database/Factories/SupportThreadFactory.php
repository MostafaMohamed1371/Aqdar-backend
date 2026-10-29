<?php

namespace App\Modules\Crm\Database\Factories;

use App\Core\Enums\SupportThreadStatus;
use App\Modules\Crm\Models\SupportThread;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportThread> */
class SupportThreadFactory extends Factory
{
    protected $model = SupportThread::class;

    public function definition(): array
    {
        return [
            'public_code' => 'SUP-'.fake()->unique()->numerify('2026-#####'),
            'user_id' => User::factory(),
            'assigned_to' => null,
            'status' => SupportThreadStatus::Open,
            'consent_at' => now(),
        ];
    }
}
