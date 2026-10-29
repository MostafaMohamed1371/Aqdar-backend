<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Core\Enums\CategoryKind;
use App\Core\Enums\ContentBlockType;
use App\Core\Enums\DistressedChild;
use App\Core\Enums\MarketSegment;
use App\Modules\Catalog\Models\Amenity;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\ContentBlock;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Category\Models\Category;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedLocations();
        $this->seedDevelopers();
        $this->seedUnitTypes();
        $this->seedAmenities();
        $this->seedTags();
        $this->seedContent();
    }

    private function seedCategories(): void
    {
        $distressed = Category::query()->updateOrCreate(
            ['slug' => 'distressed'],
            [
                'name' => 'السوق المتعثر',
                'name_ar' => 'السوق المتعثر',
                'name_en' => 'Distressed',
                'kind' => CategoryKind::Segment,
                'segment' => MarketSegment::Distressed,
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 1,
                'help_ar' => 'وحدات عليها تعثر سداد قائم أو متوقع. التصنيف DEC-01 بانتظار اعتماد العميل.',
                'help_en' => 'Units with a current or expected payment default. Labels pending DEC-01.',
            ],
        );

        Category::query()->updateOrCreate(
            ['slug' => 'distressed-a'],
            [
                'name' => 'أ - تعثر سداد قائم',
                'name_ar' => 'أ - تعثر سداد قائم',
                'name_en' => 'A - current payment default',
                'kind' => CategoryKind::DistressedChild,
                'segment' => MarketSegment::Distressed,
                'distressed_child' => DistressedChild::CurrentDefault,
                'parent_id' => $distressed->id,
                'is_active' => true,
                'sort_order' => 1,
                'help_ar' => 'المالك لديه أقساط متأخرة أو خطر على العقد.',
                'help_en' => 'The owner has overdue installments or contract risk.',
            ],
        );

        Category::query()->updateOrCreate(
            ['slug' => 'distressed-b'],
            [
                'name' => 'ب - تعثر سداد متوقع',
                'name_ar' => 'ب - تعثر سداد متوقع',
                'name_en' => 'B - expected payment default',
                'kind' => CategoryKind::DistressedChild,
                'segment' => MarketSegment::Distressed,
                'distressed_child' => DistressedChild::ExpectedDefault,
                'parent_id' => $distressed->id,
                'is_active' => true,
                'sort_order' => 2,
                'help_ar' => 'المالك يتوقع عدم القدرة على استكمال الأقساط.',
                'help_en' => 'The owner expects not to complete the remaining installments.',
            ],
        );

        Category::query()->updateOrCreate(
            ['slug' => 'non-distressed'],
            [
                'name' => 'غير متعثر',
                'name_ar' => 'غير متعثر',
                'name_en' => 'Non-distressed',
                'kind' => CategoryKind::Segment,
                'segment' => MarketSegment::NonDistressed,
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 2,
                'help_ar' => 'بيع اختياري أو تنازل بدون تعثر.',
                'help_en' => 'Optional sale or assignment without payment default.',
            ],
        );
    }

    private function seedLocations(): void
    {
        $governorates = [
            ['cairo', 'القاهرة', 'Cairo', 1, [
                ['nasr-city', 'مدينة نصر', 'Nasr City'],
                ['new-cairo', 'القاهرة الجديدة', 'New Cairo'],
                ['maadi', 'المعادي', 'Maadi'],
                ['heliopolis', 'مصر الجديدة', 'Heliopolis'],
            ]],
            ['giza', 'الجيزة', 'Giza', 2, [
                ['6th-october', 'السادس من أكتوبر', '6th of October'],
                ['sheikh-zayed', 'الشيخ زايد', 'Sheikh Zayed'],
                ['dokki', 'الدقي', 'Dokki'],
            ]],
            ['alexandria', 'الإسكندرية', 'Alexandria', 3, [
                ['smouha', 'سموحة', 'Smouha'],
                ['montaza', 'المنتزه', 'Montaza'],
            ]],
            ['dakahlia', 'الدقهلية', 'Dakahlia', 4, []],
            ['sharqia', 'الشرقية', 'Sharqia', 5, []],
            ['qalyubia', 'القليوبية', 'Qalyubia', 6, []],
            ['beheira', 'البحيرة', 'Beheira', 7, []],
            ['minya', 'المنيا', 'Minya', 8, []],
            ['asyut', 'أسيوط', 'Asyut', 9, []],
            ['suez', 'السويس', 'Suez', 10, []],
            ['ismailia', 'الإسماعيلية', 'Ismailia', 11, []],
            ['port-said', 'بورسعيد', 'Port Said', 12, []],
            ['damietta', 'دمياط', 'Damietta', 13, []],
            ['gharbia', 'الغربية', 'Gharbia', 14, []],
            ['monufia', 'المنوفية', 'Monufia', 15, []],
            ['kafr-el-sheikh', 'كفر الشيخ', 'Kafr El Sheikh', 16, []],
            ['beni-suef', 'بني سويف', 'Beni Suef', 17, []],
            ['fayoum', 'الفيوم', 'Fayoum', 18, []],
            ['sohag', 'سوهاج', 'Sohag', 19, []],
            ['qena', 'قنا', 'Qena', 20, []],
            ['aswan', 'أسوان', 'Aswan', 21, []],
            ['luxor', 'الأقصر', 'Luxor', 22, []],
            ['red-sea', 'البحر الأحمر', 'Red Sea', 23, []],
            ['new-valley', 'الوادي الجديد', 'New Valley', 24, []],
            ['matrouh', 'مطروح', 'Matrouh', 25, []],
            ['north-sinai', 'شمال سيناء', 'North Sinai', 26, []],
            ['south-sinai', 'جنوب سيناء', 'South Sinai', 27, []],
        ];

        foreach ($governorates as [$slug, $ar, $en, $order, $areas]) {
            $governorate = Governorate::query()->updateOrCreate(
                ['slug' => $slug],
                ['name_ar' => $ar, 'name_en' => $en, 'is_active' => true, 'sort_order' => $order],
            );

            foreach ($areas as $index => [$areaSlug, $areaAr, $areaEn]) {
                Area::query()->updateOrCreate(
                    ['governorate_id' => $governorate->id, 'slug' => $areaSlug],
                    ['name_ar' => $areaAr, 'name_en' => $areaEn, 'is_active' => true, 'sort_order' => $index + 1],
                );
            }
        }
    }

    private function seedDevelopers(): void
    {
        $other = Developer::query()->updateOrCreate(
            ['slug' => 'other'],
            [
                'name_ar' => 'أخرى',
                'name_en' => 'Other',
                'is_other' => true,
                'is_active' => true,
                'sort_order' => 99,
            ],
        );

        $tmg = Developer::query()->updateOrCreate(
            ['slug' => 'tmg'],
            [
                'name_ar' => 'طلعت مصطفى',
                'name_en' => 'Talaat Moustafa Group',
                'is_other' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Project::query()->updateOrCreate(
            ['developer_id' => $tmg->id, 'slug' => 'madinaty'],
            ['name_ar' => 'مدينتي', 'name_en' => 'Madinaty', 'is_other' => false, 'is_active' => true, 'sort_order' => 1],
        );

        Project::query()->updateOrCreate(
            ['developer_id' => $other->id, 'slug' => 'other'],
            ['name_ar' => 'مشروع آخر', 'name_en' => 'Other project', 'is_other' => true, 'is_active' => true, 'sort_order' => 99],
        );
    }

    private function seedUnitTypes(): void
    {
        $types = [
            ['apartment', 'شقة', 'Apartment', 1],
            ['villa', 'فيلا', 'Villa', 2],
            ['townhouse', 'تاون هاوس', 'Townhouse', 3],
            ['duplex', 'دوبلكس', 'Duplex', 4],
            ['penthouse', 'بنتهاوس', 'Penthouse', 5],
            ['studio', 'استوديو', 'Studio', 6],
            ['chalet', 'شاليه', 'Chalet', 7],
            ['land', 'أرض', 'Land', 8],
            ['office', 'مكتب', 'Office', 9],
            ['shop', 'محل', 'Shop', 10],
        ];

        foreach ($types as [$slug, $ar, $en, $order]) {
            UnitType::query()->updateOrCreate(
                ['slug' => $slug],
                ['name_ar' => $ar, 'name_en' => $en, 'is_active' => true, 'sort_order' => $order],
            );
        }
    }

    private function seedAmenities(): void
    {
        $items = [
            ['parking', 'جراج', 'Parking', 1],
            ['elevator', 'مصعد', 'Elevator', 2],
            ['security', 'أمن', 'Security', 3],
            ['pool', 'حمام سباحة', 'Pool', 4],
            ['garden', 'حديقة', 'Garden', 5],
            ['gym', 'جيم', 'Gym', 6],
        ];

        foreach ($items as [$slug, $ar, $en, $order]) {
            Amenity::query()->updateOrCreate(
                ['slug' => $slug],
                ['name_ar' => $ar, 'name_en' => $en, 'is_active' => true, 'sort_order' => $order],
            );
        }
    }

    private function seedTags(): void
    {
        $items = [
            ['featured', 'مميز', 'Featured', 1],
            ['verified', 'موثق', 'Verified', 2],
            ['resale', 'إعادة بيع', 'Resale', 3],
        ];

        foreach ($items as [$slug, $ar, $en, $order]) {
            Tag::query()->updateOrCreate(
                ['slug' => $slug],
                ['name_ar' => $ar, 'name_en' => $en, 'is_active' => true, 'sort_order' => $order],
            );
        }
    }

    private function seedContent(): void
    {
        ContentBlock::query()->updateOrCreate(
            ['type' => ContentBlockType::Banner, 'key' => 'home-hero'],
            [
                'title_ar' => 'عَقدار — خروج وإعادة بيع في مصر',
                'title_en' => 'AQDAR — exit and resale in Egypt',
                'body_ar' => 'اعرض وحدتك أو ابحث عن فرصة. التواصل يتم عبر فريق المبيعات فقط.',
                'body_en' => 'List your unit or browse opportunities. Contact goes through Sales only.',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        ContentBlock::query()->updateOrCreate(
            ['type' => ContentBlockType::Faq, 'key' => 'what-is-distressed'],
            [
                'title_ar' => 'ما هو السوق المتعثر؟',
                'title_en' => 'What is the distressed market?',
                'body_ar' => 'وحدات عليها تعثر سداد قائم (أ) أو متوقع (ب). التصنيف النهائي يعتمد قرار العميل DEC-01.',
                'body_en' => 'Units with a current (A) or expected (B) payment default. Final labels follow DEC-01.',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        ContentBlock::query()->updateOrCreate(
            ['type' => ContentBlockType::Faq, 'key' => 'how-contact-works'],
            [
                'title_ar' => 'كيف يتم التواصل؟',
                'title_en' => 'How does contact work?',
                'body_ar' => 'لا يظهر هاتف البائع أو المشتري في التطبيق. كل اهتمام يتحول إلى استفسار لفريق المبيعات.',
                'body_en' => 'Buyer and seller phones never appear in the app. Interest becomes a Sales inquiry.',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        ContentBlock::query()->updateOrCreate(
            ['type' => ContentBlockType::Support, 'key' => 'support-hours'],
            [
                'title_ar' => 'الدعم',
                'title_en' => 'Support',
                'body_ar' => 'للاستفسار عن الوحدات استخدم رقم المبيعات من إعدادات التطبيق. لا توجد محادثة مباشرة بين الأطراف.',
                'body_en' => 'For listing questions use the Sales number from app config. There is no buyer–seller chat.',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        ContentBlock::query()->updateOrCreate(
            ['type' => ContentBlockType::Support, 'key' => 'exit_estimate'],
            [
                'title_ar' => 'تقدير الخروج',
                'title_en' => 'Exit estimate',
                'body_ar' => 'تقدير استرشادي غير ملزم وليس تقييماً معتمداً أو استشارة قانونية. راجع العقد مع مستشار قانوني قبل اتخاذ أي قرار.',
                'body_en' => 'This is a non-binding estimate, not a certified valuation or legal advice. Review the contract with a legal advisor before you decide.',
                'is_active' => true,
                'sort_order' => 20,
            ],
        );
    }
}
