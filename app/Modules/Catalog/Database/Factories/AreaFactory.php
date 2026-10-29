<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\Governorate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Area> */
class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        $name = fake()->unique()->streetName();

        return [
            'governorate_id' => Governorate::factory(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'name_ar' => $name,
            'name_en' => $name,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
