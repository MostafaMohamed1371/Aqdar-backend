<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'developer_id' => Developer::factory(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'name_ar' => $name,
            'name_en' => $name,
            'is_other' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
