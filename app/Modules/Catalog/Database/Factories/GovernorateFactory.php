<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Governorate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Governorate> */
class GovernorateFactory extends Factory
{
    protected $model = Governorate::class;

    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'name_ar' => $name,
            'name_en' => $name,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
