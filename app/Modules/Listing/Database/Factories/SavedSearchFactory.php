<?php

namespace App\Modules\Listing\Database\Factories;

use App\Modules\Listing\Models\SavedSearch;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SavedSearch> */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'بحث محفوظ',
            'filters' => [],
            'notify' => true,
        ];
    }
}
