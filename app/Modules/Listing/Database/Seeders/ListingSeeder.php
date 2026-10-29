<?php

namespace App\Modules\Listing\Database\Seeders;

use App\Core\Enums\DistressedChild;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\MarketSegment;
use App\Core\Enums\PublicCodePrefix;
use App\Core\Identity\PublicCodeGenerator;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Category\Models\Category;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'seller@example.com'],
            [
                'name' => 'Seller Demo',
                'password' => 'password',
                'phone' => '+201011112222',
                'is_buyer' => true,
                'is_seller' => true,
            ],
        );

        $nonDistressed = Category::query()->where('slug', 'non-distressed')->first();
        $distressedA = Category::query()->where('slug', 'distressed-a')->first();
        $area = Area::query()->where('slug', 'nasr-city')->first();
        $unitType = UnitType::query()->where('slug', 'apartment')->first();
        $developer = Developer::query()->where('slug', 'tmg')->first();
        $project = Project::query()->where('slug', 'madinaty')->first();

        if ($nonDistressed === null || $area === null || $unitType === null) {
            return;
        }

        $this->seedPublished(
            $user,
            'AQ-0001',
            $nonDistressed,
            MarketSegment::NonDistressed,
            null,
            $area,
            $unitType,
            $developer,
            $project,
            'شقة مدينتي للبيع',
            'Madinaty apartment for sale',
        );

        if ($distressedA !== null) {
            $this->seedPublished(
                $user,
                'AQ-0002',
                $distressedA,
                MarketSegment::Distressed,
                DistressedChild::CurrentDefault,
                $area,
                $unitType,
                $developer,
                $project,
                'وحدة متعثرة مدينة نصر',
                'Distressed unit in Nasr City',
            );
        }
    }

    private function seedPublished(
        User $user,
        string $code,
        Category $category,
        MarketSegment $segment,
        ?DistressedChild $child,
        Area $area,
        UnitType $unitType,
        ?Developer $developer,
        ?Project $project,
        string $titleAr,
        string $titleEn,
    ): void {
        $codes = app(PublicCodeGenerator::class);
        $existing = Listing::query()->where('public_code', $code)->first();

        if ($existing === null) {
            $generated = $codes->next(PublicCodePrefix::Listing);
            $code = str_starts_with($generated, 'AQ-') ? $code : $generated;
        }

        $listing = Listing::query()->updateOrCreate(
            ['public_code' => $code],
            [
                'user_id' => $user->id,
                'status' => ListingStatus::Published,
                'category_id' => $category->id,
                'segment' => $segment,
                'distressed_child' => $child,
                'governorate_id' => $area->governorate_id,
                'area_id' => $area->id,
                'developer_id' => $developer?->id,
                'project_id' => $project?->id,
                'unit_type_id' => $unitType->id,
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'description_ar' => 'وحدة تجريبية للبحث العام. بدون رقم هاتف في الوصف.',
                'description_en' => 'Demo unit for public search. No phone number in the description.',
                'area_sqm' => 140,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'floor' => 5,
                'finishing' => 'finished',
                'lat' => 30.0444,
                'lng' => 31.2357,
                'consent_at' => now(),
                'submitted_at' => now(),
                'published_at' => now(),
                'version' => 1,
                'review_reference' => $code.'-R1',
            ],
        );

        $listing->financials()->updateOrCreate(
            ['listing_id' => $listing->id],
            [
                'declared_contract_price' => 2500000,
                'declared_total_paid' => 900000,
                'declared_remaining' => 1600000,
                'declared_installment_amount' => 18000,
                'declared_remaining_installments' => 90,
                'declared_asking_price' => 2300000,
            ],
        );
    }
}
