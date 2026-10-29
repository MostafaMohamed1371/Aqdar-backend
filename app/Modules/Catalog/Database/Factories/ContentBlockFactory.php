<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Core\Enums\ContentBlockType;
use App\Modules\Catalog\Models\ContentBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentBlock> */
class ContentBlockFactory extends Factory
{
    protected $model = ContentBlock::class;

    public function definition(): array
    {
        return [
            'type' => ContentBlockType::Faq,
            'key' => fake()->unique()->slug(2),
            'title_ar' => fake()->sentence(3),
            'title_en' => fake()->sentence(3),
            'body_ar' => fake()->paragraph(),
            'body_en' => fake()->paragraph(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
