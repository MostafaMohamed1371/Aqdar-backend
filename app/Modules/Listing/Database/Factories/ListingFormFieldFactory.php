<?php

namespace App\Modules\Listing\Database\Factories;

use App\Core\Enums\FormFieldType;
use App\Modules\Category\Models\Category;
use App\Modules\Listing\Models\ListingFormField;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ListingFormField> */
class ListingFormFieldFactory extends Factory
{
    protected $model = ListingFormField::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'form_version' => 1,
            'field_key' => 'parking_spaces',
            'label_ar' => 'عدد مواقف السيارات',
            'label_en' => 'Parking spaces',
            'type' => FormFieldType::Number,
            'options' => null,
            'is_required' => false,
            'sort_order' => 0,
        ];
    }

    public function required(): static
    {
        return $this->state(fn (): array => ['is_required' => true]);
    }
}
