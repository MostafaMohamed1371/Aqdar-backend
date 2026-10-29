<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<UnitType> */
class UnitTypeFactory extends Factory
{
    protected $model = UnitType::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'name_ar' => $name,
            'name_en' => $name,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
