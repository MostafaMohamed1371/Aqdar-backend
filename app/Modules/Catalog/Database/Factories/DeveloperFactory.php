<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Developer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Developer> */
class DeveloperFactory extends Factory
{
    protected $model = Developer::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'name_ar' => $name,
            'name_en' => $name,
            'is_other' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
