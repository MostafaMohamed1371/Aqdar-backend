<?php

namespace App\Modules\Staff\Database\Factories;

use App\Modules\Staff\Models\StaffTeam;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StaffTeam> */
class StaffTeamFactory extends Factory
{
    protected $model = StaffTeam::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'name_ar' => $name,
            'name_en' => $name,
            'sales_phone' => null,
            'sales_hours_ar' => null,
            'sales_hours_en' => null,
            'is_active' => true,
        ];
    }

    public function withSalesNumber(string $phone = '+20 100 000 0000'): static
    {
        return $this->state(fn (): array => ['sales_phone' => $phone]);
    }
}
