<?php

namespace App\Modules\Setting\Database\Factories;

use App\Modules\Setting\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Setting> */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'value' => fake()->sentence(),
            'group' => fake()->randomElement(['general', 'payment', 'mail']),
        ];
    }
}
