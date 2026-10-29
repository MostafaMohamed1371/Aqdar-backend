<?php

namespace App\Modules\Listing\Database\Factories;

use App\Core\Enums\ListingStatus;
use App\Core\Enums\MarketSegment;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Category\Models\Category;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingFinancial;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Listing> */
class ListingFactory extends Factory
{
    protected $model = Listing::class;

    public function definition(): array
    {
        $governorate = Governorate::factory()->create();
        $area = Area::factory()->create(['governorate_id' => $governorate->id]);

        return [
            'public_code' => 'AQ-'.fake()->unique()->numerify('####'),
            'user_id' => User::factory()->seller(),
            'status' => ListingStatus::Draft,
            'category_id' => Category::factory(),
            'segment' => MarketSegment::NonDistressed,
            'distressed_child' => null,
            'governorate_id' => $governorate->id,
            'area_id' => $area->id,
            'unit_type_id' => UnitType::factory(),
            'title_ar' => 'شقة للبيع',
            'title_en' => 'Apartment for sale',
            'description_ar' => 'وحدة إعادة بيع بدون بيانات تواصل في الوصف.',
            'description_en' => 'Resale unit with no contact details in the description.',
            'area_sqm' => 120,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'floor' => 4,
            'finishing' => 'finished',
            'lat' => 30.0444,
            'lng' => 31.2357,
            'contact_flagged' => false,
            'version' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Listing $listing): void {
            if ($listing->financials()->doesntExist()) {
                ListingFinancial::factory()->create(['listing_id' => $listing->id]);
            }
        });
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ListingStatus::Published,
            'consent_at' => now(),
            'submitted_at' => now(),
            'published_at' => now(),
            'version' => 1,
            'review_reference' => 'AQ-SEED-R1',
        ]);
    }

    public function needsChanges(): static
    {
        return $this->state(fn () => [
            'status' => ListingStatus::NeedsChanges,
            'consent_at' => now(),
            'submitted_at' => now(),
            'version' => 1,
        ]);
    }
}
