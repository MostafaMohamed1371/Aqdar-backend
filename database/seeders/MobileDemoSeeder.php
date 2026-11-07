<?php

namespace Database\Seeders;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\BuyerIntent;
use App\Core\Enums\BuyerRequestStatus;
use App\Core\Enums\CaseStage;
use App\Core\Enums\CaseStatus;
use App\Core\Enums\ContactMethod;
use App\Core\Enums\ContentBlockType;
use App\Core\Enums\DistressedChild;
use App\Core\Enums\DocumentReviewStatus;
use App\Core\Enums\DocumentType;
use App\Core\Enums\InquiryStatus;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\MarketSegment;
use App\Core\Enums\MatchBuyerResponse;
use App\Core\Enums\MediaModerationStatus;
use App\Core\Enums\NotificationCategory;
use App\Core\Enums\PreferredTime;
use App\Core\Enums\PublicCodePrefix;
use App\Core\Enums\SegmentPreference;
use App\Core\Enums\SupportAuthorType;
use App\Core\Enums\SupportThreadStatus;
use App\Core\Identity\PublicCodeGenerator;
use App\Core\Support\DemoPhoto;
use App\Modules\Catalog\Models\Amenity;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\ContentBlock;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Category\Models\Category;
use App\Modules\Crm\Models\Appointment;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\CaseHistory;
use App\Modules\Crm\Models\Inquiry;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\RequestMatch;
use App\Modules\Crm\Models\SupportMessage;
use App\Modules\Crm\Models\SupportThread;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Listing\Models\Favorite;
use App\Modules\Listing\Models\InstallmentItem;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use App\Modules\Listing\Models\ListingMedia;
use App\Modules\Listing\Models\ListingStatusHistory;
use App\Modules\Listing\Models\ListingView;
use App\Modules\Listing\Models\SavedSearch;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Setting\Models\Setting;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Rich Egypt marketplace demo data for mobile API screens.
 *
 * Run after CatalogSeeder. Idempotent via public_code / email keys.
 */
class MobileDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensurePublicMediaLink();

        $seller = User::query()->updateOrCreate(
            ['email' => 'seller@example.com'],
            [
                'name' => 'بائع تجريبي',
                'password' => 'password',
                'phone' => '+201011112222',
                'phone_verified_at' => now(),
                'is_buyer' => true,
                'is_seller' => true,
                'locale' => 'ar',
                'consent_privacy_at' => now(),
                'consent_terms_at' => now(),
                'consent_version' => (string) config('aqdar.consent.version', '1'),
            ],
        );

        $buyer = User::query()->where('email', 'test@example.com')->first()
            ?? User::query()->where('email', 'buyer@example.com')->first();

        $catalog = $this->resolveCatalog();

        if ($catalog === null) {
            $this->command?->warn('MobileDemoSeeder skipped: run CatalogSeeder first.');

            return;
        }

        $listings = $this->seedListings($seller, $catalog);
        $this->seedDeveloperLogos($catalog['developers']);
        $this->seedContentImages();
        $this->seedSellerDesk($seller, $listings);
        $this->ensureSalesPhone();

        $buyers = User::query()
            ->where('is_buyer', true)
            ->whereKeyNot($seller->id)
            ->get();

        if ($buyer !== null && $buyers->doesntContain(fn (User $user): bool => $user->id === $buyer->id)) {
            $buyers->push($buyer);
        }

        foreach ($buyers as $marketplaceBuyer) {
            $this->seedBuyerExperience($marketplaceBuyer, $listings, $catalog);
        }
    }

    /**
     * @return array{
     *     nonDistressed: Category,
     *     distressedA: ?Category,
     *     distressedB: ?Category,
     *     areas: array<string, Area>,
     *     unitTypes: array<string, UnitType>,
     *     developers: array<string, Developer>,
     *     projects: array<string, Project>,
     *     amenityIds: list<int>,
     *     tagIds: array<string, int>
     * }|null
     */
    private function resolveCatalog(): ?array
    {
        $nonDistressed = Category::query()->where('slug', 'non-distressed')->first();
        $area = Area::query()->where('slug', 'nasr-city')->first();
        $unitType = UnitType::query()->where('slug', 'apartment')->first();

        if ($nonDistressed === null || $area === null || $unitType === null) {
            return null;
        }

        $areas = Area::query()
            ->whereIn('slug', [
                'nasr-city', 'new-cairo', 'maadi', 'heliopolis',
                '6th-october', 'sheikh-zayed', 'smouha',
            ])
            ->get()
            ->keyBy('slug');

        $unitTypes = UnitType::query()
            ->whereIn('slug', ['apartment', 'villa', 'townhouse', 'duplex', 'chalet', 'studio'])
            ->get()
            ->keyBy('slug');

        $developers = Developer::query()
            ->whereIn('slug', ['tmg', 'palm-hills', 'sodic', 'emaar', 'mountain-view', 'ora'])
            ->get()
            ->keyBy('slug');

        $projects = Project::query()
            ->whereIn('slug', [
                'madinaty', 'rehab', 'badya', 'palm-parks', 'villette', 'eastown',
                'uptown-cairo', 'marassi', 'icity', 'october-park', 'zelda',
            ])
            ->get()
            ->keyBy('slug');

        return [
            'nonDistressed' => $nonDistressed,
            'distressedA' => Category::query()->where('slug', 'distressed-a')->first(),
            'distressedB' => Category::query()->where('slug', 'distressed-b')->first(),
            'areas' => $areas->all(),
            'unitTypes' => $unitTypes->all(),
            'developers' => $developers->all(),
            'projects' => $projects->all(),
            'amenityIds' => Amenity::query()->where('is_active', true)->orderBy('sort_order')->pluck('id')->all(),
            'tagIds' => Tag::query()->where('is_active', true)->pluck('id', 'slug')->all(),
        ];
    }

    /**
     * @param  array{
     *     nonDistressed: Category,
     *     distressedA: ?Category,
     *     distressedB: ?Category,
     *     areas: array<string, Area>,
     *     unitTypes: array<string, UnitType>,
     *     developers: array<string, Developer>,
     *     projects: array<string, Project>,
     *     amenityIds: list<int>,
     *     tagIds: array<string, int>
     * }  $catalog
     * @return list<Listing>
     */
    private function seedListings(User $seller, array $catalog): array
    {
        $defs = [
            [
                'code' => 'AQ-1001',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::NonDistressed,
                'child' => null,
                'category' => 'nonDistressed',
                'area' => 'new-cairo',
                'unit' => 'apartment',
                'developer' => 'sodic',
                'project' => 'villette',
                'title_ar' => 'شقة فيليت القاهرة الجديدة',
                'title_en' => 'Villette apartment in New Cairo',
                'description_ar' => 'شقة إعادة بيع بموقع هادئ داخل الكمبوند. تشطيب كامل وإطلالة حديقة. التواصل عبر فريق المبيعات فقط.',
                'description_en' => 'Resale apartment in a quiet compound location. Fully finished with a garden view. Contact through Sales only.',
                'area_sqm' => 165,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'floor' => 3,
                'finishing' => 'finished',
                'lat' => 30.0275,
                'lng' => 31.4942,
                'asking' => 4200000,
                'paid' => 1800000,
                'remaining' => 2400000,
                'installment' => 28000,
                'installments_left' => 86,
                'tags' => ['featured', 'resale'],
                'photos' => 4,
                'verified_asking' => true,
            ],
            [
                'code' => 'AQ-1002',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::NonDistressed,
                'child' => null,
                'category' => 'nonDistressed',
                'area' => 'sheikh-zayed',
                'unit' => 'villa',
                'developer' => 'palm-hills',
                'project' => 'badya',
                'title_ar' => 'فيلا بادية الشيخ زايد',
                'title_en' => 'Badya villa in Sheikh Zayed',
                'description_ar' => 'فيلا مستقلة بحديقة خاصة وموقفين. مناسبة للسكن العائلي مع خدمات الكمبوند.',
                'description_en' => 'Standalone villa with a private garden and two parking spots. Family living with compound services.',
                'area_sqm' => 280,
                'bedrooms' => 4,
                'bathrooms' => 4,
                'floor' => 0,
                'finishing' => 'semi_finished',
                'furnished' => 'unfurnished',
                'lat' => 30.0261,
                'lng' => 30.9784,
                'asking' => 9500000,
                'paid' => 4200000,
                'remaining' => 5300000,
                'installment' => 55000,
                'installments_left' => 96,
                'tags' => ['featured', 'verified'],
                'photos' => 5,
                'verified_asking' => true,
            ],
            [
                'code' => 'AQ-1003',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::Distressed,
                'child' => DistressedChild::CurrentDefault,
                'category' => 'distressedA',
                'area' => 'nasr-city',
                'unit' => 'apartment',
                'developer' => 'tmg',
                'project' => 'madinaty',
                'title_ar' => 'شقة مدينتي — تعثر سداد قائم',
                'title_en' => 'Madinaty apartment — current default',
                'description_ar' => 'وحدة عليها أقساط متأخرة. الفرصة مناسبة لمشترٍ يبحث عن سعر خروج مع مراجعة العقد عبر المبيعات.',
                'description_en' => 'Unit with overdue installments. Suited for a buyer seeking an exit price with contract review via Sales.',
                'area_sqm' => 140,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'floor' => 5,
                'finishing' => 'finished',
                'lat' => 30.0921,
                'lng' => 31.6631,
                'asking' => 2300000,
                'paid' => 900000,
                'remaining' => 1600000,
                'installment' => 18000,
                'installments_left' => 90,
                'overdue_count' => 4,
                'overdue_amount' => 72000,
                'tags' => ['resale'],
                'photos' => 3,
                'installment_plan' => true,
            ],
            [
                'code' => 'AQ-1004',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::Distressed,
                'child' => DistressedChild::ExpectedDefault,
                'category' => 'distressedB',
                'area' => '6th-october',
                'unit' => 'townhouse',
                'developer' => 'mountain-view',
                'project' => 'october-park',
                'title_ar' => 'تاون هاوس أكتوبر — تعثر متوقع',
                'title_en' => 'October townhouse — expected default',
                'description_ar' => 'المالك يتوقع صعوبة استكمال الأقساط. الوحدة شبه جاهزة للتسليم داخل المشروع.',
                'description_en' => 'Owner expects difficulty completing installments. Near-handover unit inside the project.',
                'area_sqm' => 210,
                'bedrooms' => 3,
                'bathrooms' => 3,
                'floor' => 0,
                'finishing' => 'core_and_shell',
                'furnished' => 'unfurnished',
                'lat' => 29.9612,
                'lng' => 30.9218,
                'asking' => 5100000,
                'paid' => 2100000,
                'remaining' => 3000000,
                'installment' => 32000,
                'installments_left' => 94,
                'tags' => ['featured'],
                'photos' => 4,
            ],
            [
                'code' => 'AQ-1005',
                'status' => ListingStatus::UnderOffer,
                'segment' => MarketSegment::NonDistressed,
                'child' => null,
                'category' => 'nonDistressed',
                'area' => 'maadi',
                'unit' => 'apartment',
                'developer' => 'ora',
                'project' => 'zelda',
                'title_ar' => 'شقة زيد المعادي — تحت العرض',
                'title_en' => 'Zed Maadi apartment — under offer',
                'description_ar' => 'وحدة تحت عرض شراء قائم. يمكن متابعة الحالة عبر فريق المبيعات.',
                'description_en' => 'Unit currently under an active offer. Follow status through the sales team.',
                'area_sqm' => 155,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'floor' => 8,
                'finishing' => 'finished',
                'lat' => 29.9602,
                'lng' => 31.2769,
                'asking' => 6100000,
                'paid' => 6100000,
                'remaining' => 0,
                'installment' => 0,
                'installments_left' => 0,
                'tags' => ['verified'],
                'photos' => 3,
            ],
            [
                'code' => 'AQ-1006',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::NonDistressed,
                'child' => null,
                'category' => 'nonDistressed',
                'area' => 'heliopolis',
                'unit' => 'duplex',
                'developer' => 'emaar',
                'project' => 'uptown-cairo',
                'title_ar' => 'دوبلكس أب تاون القاهرة',
                'title_en' => 'Uptown Cairo duplex',
                'description_ar' => 'دوبلكس بإطلالة مفتوحة وتشطيب فاخر. مناسب للسكن الراقي داخل المشروع.',
                'description_en' => 'Duplex with an open view and premium finishing. Upscale living inside the project.',
                'area_sqm' => 240,
                'bedrooms' => 4,
                'bathrooms' => 3,
                'floor' => 10,
                'finishing' => 'finished',
                'lat' => 30.0894,
                'lng' => 31.3461,
                'asking' => 7800000,
                'paid' => 3500000,
                'remaining' => 4300000,
                'installment' => 45000,
                'installments_left' => 95,
                'tags' => ['featured', 'verified'],
                'photos' => 4,
            ],
            [
                'code' => 'AQ-1007',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::NonDistressed,
                'child' => null,
                'category' => 'nonDistressed',
                'area' => 'smouha',
                'unit' => 'apartment',
                'developer' => 'emaar',
                'project' => 'marassi',
                'title_ar' => 'شقة مراسي الساحل — إعادة بيع',
                'title_en' => 'Marassi North Coast resale',
                'description_ar' => 'فرصة إعادة بيع قرب الخدمات البحرية. الوحدة جاهزة للتسليم مع أثاث اختياري.',
                'description_en' => 'Resale opportunity near coastal services. Ready for handover with optional furniture.',
                'area_sqm' => 120,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'floor' => 2,
                'finishing' => 'finished',
                'furnished' => 'furnished',
                'lat' => 31.2156,
                'lng' => 29.9553,
                'asking' => 3900000,
                'paid' => 3900000,
                'remaining' => 0,
                'installment' => 0,
                'installments_left' => 0,
                'tags' => ['resale'],
                'photos' => 3,
            ],
            [
                'code' => 'AQ-1008',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::Distressed,
                'child' => DistressedChild::CurrentDefault,
                'category' => 'distressedA',
                'area' => 'new-cairo',
                'unit' => 'apartment',
                'developer' => 'sodic',
                'project' => 'eastown',
                'title_ar' => 'شقة إيستاون — أقساط متأخرة',
                'title_en' => 'Eastown apartment — overdue installments',
                'description_ar' => 'وحدة عليها متأخرات محدودة. السعر يعكس وضع الخروج الحالي بعد مراجعة المبيعات.',
                'description_en' => 'Unit with limited arrears. Price reflects the current exit position after sales review.',
                'area_sqm' => 130,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'floor' => 6,
                'finishing' => 'finished',
                'lat' => 30.0168,
                'lng' => 31.4915,
                'asking' => 2750000,
                'paid' => 1100000,
                'remaining' => 1650000,
                'installment' => 22000,
                'installments_left' => 75,
                'overdue_count' => 2,
                'overdue_amount' => 44000,
                'tags' => ['resale'],
                'photos' => 3,
                'installment_plan' => true,
            ],
            [
                'code' => 'AQ-1009',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::NonDistressed,
                'child' => null,
                'category' => 'nonDistressed',
                'area' => 'sheikh-zayed',
                'unit' => 'townhouse',
                'developer' => 'palm-hills',
                'project' => 'palm-parks',
                'title_ar' => 'تاون هاوس بالم باركس',
                'title_en' => 'Palm Parks townhouse',
                'description_ar' => 'تاون هاوس بمدخل خاص وحديقة صغيرة. التشطيب نصف مكتمل ويمكن تخصيصه.',
                'description_en' => 'Townhouse with a private entrance and small garden. Semi-finished and customizable.',
                'area_sqm' => 195,
                'bedrooms' => 3,
                'bathrooms' => 3,
                'floor' => 0,
                'finishing' => 'semi_finished',
                'furnished' => 'unfurnished',
                'lat' => 30.0412,
                'lng' => 30.9876,
                'asking' => 5600000,
                'paid' => 2400000,
                'remaining' => 3200000,
                'installment' => 36000,
                'installments_left' => 88,
                'tags' => ['featured'],
                'photos' => 4,
            ],
            [
                'code' => 'AQ-1010',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::NonDistressed,
                'child' => null,
                'category' => 'nonDistressed',
                'area' => 'nasr-city',
                'unit' => 'studio',
                'developer' => 'tmg',
                'project' => 'rehab',
                'title_ar' => 'استوديو الرحاب للإيجار طويل أو إعادة البيع',
                'title_en' => 'Al Rehab studio for long stay or resale',
                'description_ar' => 'استوديو مجهز بالكامل داخل الرحاب. مناسب للاستثمار أو السكن الفردي.',
                'description_en' => 'Fully fitted studio inside Al Rehab. Suitable for investment or single living.',
                'area_sqm' => 55,
                'bedrooms' => 0,
                'bathrooms' => 1,
                'floor' => 4,
                'finishing' => 'finished',
                'furnished' => 'furnished',
                'lat' => 30.0664,
                'lng' => 31.4912,
                'asking' => 980000,
                'paid' => 980000,
                'remaining' => 0,
                'installment' => 0,
                'installments_left' => 0,
                'tags' => ['resale'],
                'photos' => 2,
            ],
            [
                'code' => 'AQ-1011',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::NonDistressed,
                'child' => null,
                'category' => 'nonDistressed',
                'area' => '6th-october',
                'unit' => 'chalet',
                'developer' => 'mountain-view',
                'project' => 'icity',
                'title_ar' => 'شاليه آي سيتي أكتوبر',
                'title_en' => 'iCity October chalet',
                'description_ar' => 'وحدة ترفيهية بإطلالة مسبح. مناسبة كمنزل ثانٍ أو استثمار موسمي.',
                'description_en' => 'Leisure unit overlooking the pool. Ideal as a second home or seasonal investment.',
                'area_sqm' => 90,
                'bedrooms' => 2,
                'bathrooms' => 1,
                'floor' => 1,
                'finishing' => 'finished',
                'lat' => 29.9725,
                'lng' => 30.9341,
                'asking' => 2100000,
                'paid' => 900000,
                'remaining' => 1200000,
                'installment' => 15000,
                'installments_left' => 80,
                'tags' => ['featured'],
                'photos' => 3,
            ],
            [
                'code' => 'AQ-1012',
                'status' => ListingStatus::UnderOffer,
                'segment' => MarketSegment::Distressed,
                'child' => DistressedChild::CurrentDefault,
                'category' => 'distressedA',
                'area' => 'maadi',
                'unit' => 'apartment',
                'developer' => 'tmg',
                'project' => 'madinaty',
                'title_ar' => 'شقة متعثرة تحت عرض شراء',
                'title_en' => 'Distressed apartment under offer',
                'description_ar' => 'وحدة متعثرة دخلت مرحلة عرض شراء. التفاصيل المالية تُراجع مع المبيعات.',
                'description_en' => 'Distressed unit that entered an under-offer stage. Financial details are reviewed with Sales.',
                'area_sqm' => 125,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'floor' => 7,
                'finishing' => 'finished',
                'lat' => 29.9701,
                'lng' => 31.2688,
                'asking' => 1950000,
                'paid' => 700000,
                'remaining' => 1250000,
                'installment' => 16000,
                'installments_left' => 78,
                'overdue_count' => 3,
                'overdue_amount' => 48000,
                'tags' => ['resale'],
                'photos' => 3,
            ],
            [
                'code' => 'AQ-1013',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::NonDistressed,
                'child' => null,
                'category' => 'nonDistressed',
                'area' => 'new-cairo',
                'unit' => 'villa',
                'developer' => 'sodic',
                'project' => 'villette',
                'title_ar' => 'فيلا فيليت للبيع الاختياري',
                'title_en' => 'Villette villa — optional sale',
                'description_ar' => 'بيع اختياري بدون تعثر. الفيلا بحديقة خلفية وموقف مغطى.',
                'description_en' => 'Optional sale with no payment default. Villa with a backyard and covered parking.',
                'area_sqm' => 320,
                'bedrooms' => 5,
                'bathrooms' => 4,
                'floor' => 0,
                'finishing' => 'finished',
                'lat' => 30.0318,
                'lng' => 31.5012,
                'asking' => 12500000,
                'paid' => 12500000,
                'remaining' => 0,
                'installment' => 0,
                'installments_left' => 0,
                'tags' => ['featured', 'verified'],
                'photos' => 5,
                'verified_asking' => true,
            ],
            [
                'code' => 'AQ-1014',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::NonDistressed,
                'child' => null,
                'category' => 'nonDistressed',
                'area' => 'heliopolis',
                'unit' => 'apartment',
                'developer' => 'palm-hills',
                'project' => 'badya',
                'title_ar' => 'شقة عائلية تشطيب كامل',
                'title_en' => 'Family apartment fully finished',
                'description_ar' => 'شقة عائلية بثلاث غرف ومطبخ مجهز. قريبة من الخدمات والمدارس.',
                'description_en' => 'Family apartment with three bedrooms and a fitted kitchen. Close to services and schools.',
                'area_sqm' => 148,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'floor' => 4,
                'finishing' => 'finished',
                'lat' => 30.0912,
                'lng' => 31.3288,
                'asking' => 3400000,
                'paid' => 1400000,
                'remaining' => 2000000,
                'installment' => 24000,
                'installments_left' => 83,
                'tags' => ['resale'],
                'photos' => 3,
            ],
            [
                'code' => 'AQ-1015',
                'status' => ListingStatus::Published,
                'segment' => MarketSegment::Distressed,
                'child' => DistressedChild::ExpectedDefault,
                'category' => 'distressedB',
                'area' => 'sheikh-zayed',
                'unit' => 'apartment',
                'developer' => 'ora',
                'project' => 'zelda',
                'title_ar' => 'شقة زايد — تعثر سداد متوقع',
                'title_en' => 'Zayed apartment — expected default',
                'description_ar' => 'المالك يرغب في الخروج قبل تفاقم الالتزامات. الوحدة في مرحلة التشطيب الداخلي.',
                'description_en' => 'Owner wants to exit before obligations grow. Unit is in interior finishing stage.',
                'area_sqm' => 158,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'floor' => 9,
                'finishing' => 'semi_finished',
                'furnished' => 'unfurnished',
                'lat' => 30.0355,
                'lng' => 30.9921,
                'asking' => 3050000,
                'paid' => 1250000,
                'remaining' => 1800000,
                'installment' => 21000,
                'installments_left' => 86,
                'tags' => ['featured'],
                'photos' => 3,
            ],
            [
                'code' => 'AQ-DRAFT-01',
                'status' => ListingStatus::Draft,
                'segment' => MarketSegment::NonDistressed,
                'child' => null,
                'category' => 'nonDistressed',
                'area' => 'nasr-city',
                'unit' => 'apartment',
                'developer' => 'tmg',
                'project' => 'madinaty',
                'title_ar' => 'مسودة إعلان بائع',
                'title_en' => 'Seller draft listing',
                'description_ar' => 'مسودة غير منشورة لاختبار شاشة عقاراتي.',
                'description_en' => 'Unpublished draft for testing My Listings.',
                'area_sqm' => 110,
                'bedrooms' => 2,
                'bathrooms' => 1,
                'floor' => 2,
                'finishing' => 'finished',
                'lat' => 30.05,
                'lng' => 31.4,
                'asking' => 1800000,
                'paid' => 600000,
                'remaining' => 1200000,
                'installment' => 14000,
                'installments_left' => 85,
                'tags' => [],
                'photos' => 2,
            ],
            [
                'code' => 'AQ-NC-01',
                'status' => ListingStatus::NeedsChanges,
                'segment' => MarketSegment::NonDistressed,
                'child' => null,
                'category' => 'nonDistressed',
                'area' => 'new-cairo',
                'unit' => 'apartment',
                'developer' => 'sodic',
                'project' => 'eastown',
                'title_ar' => 'إعلان يحتاج تعديلاً',
                'title_en' => 'Listing needs changes',
                'description_ar' => 'إعلان أُعيد للمالك لاستكمال المستندات قبل إعادة الإرسال.',
                'description_en' => 'Returned to the owner to complete documents before resubmit.',
                'area_sqm' => 135,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'floor' => 5,
                'finishing' => 'finished',
                'lat' => 30.02,
                'lng' => 31.48,
                'asking' => 2900000,
                'paid' => 1000000,
                'remaining' => 1900000,
                'installment' => 20000,
                'installments_left' => 95,
                'tags' => [],
                'photos' => 1,
            ],
        ];

        $seeded = [];

        foreach ($defs as $def) {
            $listing = $this->upsertListing($seller, $catalog, $def);

            if ($listing !== null) {
                $seeded[] = $listing;
            }
        }

        return $seeded;
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array{
     *     nonDistressed: Category,
     *     distressedA: ?Category,
     *     distressedB: ?Category,
     *     areas: array<string, Area>,
     *     unitTypes: array<string, UnitType>,
     *     developers: array<string, Developer>,
     *     projects: array<string, Project>,
     *     amenityIds: list<int>,
     *     tagIds: array<string, int>
     * }  $catalog
     */
    private function upsertListing(User $seller, array $catalog, array $def): ?Listing
    {
        $categoryKey = $def['category'];
        $category = match ($categoryKey) {
            'distressedA' => $catalog['distressedA'],
            'distressedB' => $catalog['distressedB'],
            default => $catalog['nonDistressed'],
        };

        $area = $catalog['areas'][$def['area']] ?? null;
        $unitType = $catalog['unitTypes'][$def['unit']] ?? null;
        $developer = $catalog['developers'][$def['developer']] ?? null;
        $project = $catalog['projects'][$def['project']] ?? null;

        if ($category === null || $area === null || $unitType === null) {
            return null;
        }

        $code = $def['code'];
        $existing = Listing::query()->where('public_code', $code)->first();

        if ($existing === null) {
            $generated = app(PublicCodeGenerator::class)->next(PublicCodePrefix::Listing);
            if (! str_starts_with((string) $generated, 'AQ-')) {
                $code = $generated;
            }
        }

        $amenityIds = array_slice($catalog['amenityIds'], 0, min(4, count($catalog['amenityIds'])));
        if ($amenityIds !== []) {
            $offset = abs(crc32($def['code'])) % max(1, count($catalog['amenityIds']) - 1);
            $amenityIds = array_values(array_slice(array_merge(
                array_slice($catalog['amenityIds'], $offset),
                array_slice($catalog['amenityIds'], 0, $offset),
            ), 0, min(4, count($catalog['amenityIds']))));
        }

        $tagIds = [];
        foreach ($def['tags'] as $tagSlug) {
            if (isset($catalog['tagIds'][$tagSlug])) {
                $tagIds[] = $catalog['tagIds'][$tagSlug];
            }
        }

        /** @var ListingStatus $status */
        $status = $def['status'];
        $isPublic = $status->isPublic();

        $listing = Listing::query()->updateOrCreate(
            ['public_code' => $code],
            [
                'user_id' => $seller->id,
                'status' => $status,
                'category_id' => $category->id,
                'segment' => $def['segment'],
                'distressed_child' => $def['child'],
                'governorate_id' => $area->governorate_id,
                'area_id' => $area->id,
                'developer_id' => $developer?->id,
                'project_id' => $project?->id,
                'unit_type_id' => $unitType->id,
                'title_ar' => $def['title_ar'],
                'title_en' => $def['title_en'],
                'description_ar' => $def['description_ar'],
                'description_en' => $def['description_en'],
                'area_sqm' => $def['area_sqm'],
                'bedrooms' => $def['bedrooms'],
                'bathrooms' => $def['bathrooms'],
                'floor' => $def['floor'],
                'finishing' => $def['finishing'],
                'furnished' => $def['furnished'] ?? 'unfurnished',
                'parking' => $def['unit'] === 'villa' ? 2 : 1,
                'neighborhood' => $area->name_en,
                'lat' => $def['lat'],
                'lng' => $def['lng'],
                'amenity_ids' => $amenityIds,
                'tag_ids' => $tagIds,
                'consent_at' => $status === ListingStatus::Draft ? null : now()->subDays(5),
                'submitted_at' => $status === ListingStatus::Draft ? null : now()->subDays(4),
                'published_at' => $isPublic ? now()->subDays(2) : null,
                'offer_expires_at' => $status === ListingStatus::UnderOffer ? now()->addDays(7) : null,
                'version' => $status === ListingStatus::Draft ? 0 : 1,
                'view_count' => $isPublic ? random_int(12, 240) : 0,
                'review_reference' => $code.'-R1',
            ],
        );

        $contract = max($def['paid'] + $def['remaining'], $def['asking']);
        $cancelPercent = $def['cancel_percent'] ?? 10;

        $financialPayload = [
            'declared_contract_price' => $contract,
            'declared_total_paid' => $def['paid'],
            'declared_remaining' => $def['remaining'],
            'declared_overdue_count' => $def['overdue_count'] ?? 0,
            'declared_overdue_amount' => $def['overdue_amount'] ?? 0,
            'declared_installment_amount' => $def['installment'],
            'declared_remaining_installments' => $def['installments_left'],
            'declared_asking_price' => $def['asking'],
            'declared_cancellation_percent' => $cancelPercent,
            'declared_next_due_date' => ($def['remaining'] > 0) ? now()->addDays(20)->toDateString() : null,
        ];

        if (! empty($def['verified_asking'])) {
            $financialPayload['verified_asking_price'] = $def['asking'];
            $financialPayload['asking_price_verified'] = true;
        }

        if (! empty($def['verified_cancel'])) {
            $financialPayload['verified_cancellation_percent'] = $cancelPercent;
            $financialPayload['cancellation_percent_verified'] = true;
        }

        $listing->financials()->updateOrCreate(
            ['listing_id' => $listing->id],
            $financialPayload,
        );

        if (! empty($def['installment_plan']) && $def['installment'] > 0) {
            $this->seedInstallmentPlan($listing, (float) $def['installment']);
        }

        $photoCount = (int) ($def['photos'] ?? 0);
        if ($photoCount > 0) {
            $this->seedPhotos($listing, $photoCount);
        }

        return $listing->fresh(['financials', 'media']);
    }

    private function seedInstallmentPlan(Listing $listing, float $amount): void
    {
        InstallmentItem::query()->where('listing_id', $listing->id)->delete();

        for ($i = 1; $i <= 6; $i++) {
            InstallmentItem::query()->create([
                'listing_id' => $listing->id,
                'sequence' => $i,
                'due_date' => now()->subMonths(4 - $i)->startOfMonth()->toDateString(),
                'amount' => $amount,
                'note' => $i <= 2 ? 'overdue' : 'upcoming',
                'paid' => $i <= 2 ? false : ($i === 3),
            ]);
        }
    }

    private function seedPhotos(Listing $listing, int $count): void
    {
        $public = Storage::disk('public_media');
        $private = Storage::disk('private_media');

        for ($i = 1; $i <= $count; $i++) {
            $bytes = DemoPhoto::jpeg($listing->public_code.'-'.$i);
            $path = "listings/{$listing->id}/demo-{$i}.jpg";
            $original = "listings/{$listing->id}/original-{$i}.jpg";
            $info = @getimagesizefromstring($bytes);
            $public->put($path, $bytes);
            $private->put($original, $bytes);

            ListingMedia::query()->updateOrCreate(
                [
                    'listing_id' => $listing->id,
                    'sort_order' => $i,
                ],
                [
                    'original_disk' => 'private_media',
                    'original_path' => $original,
                    'public_path' => $path,
                    'mime' => 'image/jpeg',
                    'size_bytes' => strlen($bytes),
                    'width' => is_array($info) ? (int) $info[0] : 1200,
                    'height' => is_array($info) ? (int) $info[1] : 800,
                    'moderation_status' => MediaModerationStatus::Approved,
                    'reviewed_at' => now(),
                ],
            );
        }
    }

    /** @param  array<string, Developer>  $developers */
    private function seedDeveloperLogos(array $developers): void
    {
        $disk = Storage::disk('public_media');

        foreach ($developers as $slug => $developer) {
            $path = "developers/{$slug}.jpg";
            $disk->put($path, DemoPhoto::logo($slug));
            $developer->forceFill(['logo_path' => $path, 'is_verified' => true])->save();
        }
    }

    private function seedContentImages(): void
    {
        $disk = Storage::disk('public_media');
        $blocks = [
            [ContentBlockType::Onboarding, 'welcome'],
            [ContentBlockType::Onboarding, 'browse'],
            [ContentBlockType::Onboarding, 'sell'],
            [ContentBlockType::Onboarding, 'sales'],
            [ContentBlockType::Banner, 'home-hero'],
            [ContentBlockType::Banner, 'home-distressed'],
            [ContentBlockType::SellerGuide, 'seller-prepare'],
            [ContentBlockType::SellerGuide, 'seller-docs'],
        ];

        foreach ($blocks as [$type, $key]) {
            $path = "content/{$key}.jpg";
            $disk->put($path, DemoPhoto::jpeg('content-'.$key, 1200, 720));
            ContentBlock::query()
                ->where('type', $type)
                ->where('key', $key)
                ->update(['image_path' => $path]);
        }
    }

    private function ensurePublicMediaLink(): void
    {
        $link = public_path('storage');

        if (is_link($link) || file_exists($link)) {
            return;
        }

        $this->command?->call('storage:link', ['--no-interaction' => true]);
    }

    /**
     * @param  list<Listing>  $listings
     */
    private function seedSellerDesk(User $seller, array $listings): void
    {
        $disk = Storage::disk('private_documents');
        $bytes = DemoPhoto::jpeg('listing-document', 800, 600);

        foreach ($listings as $listing) {
            $types = [
                DocumentType::NationalId,
                DocumentType::Contract,
                DocumentType::PaymentProof,
            ];
            if ($listing->segment === MarketSegment::Distressed) {
                $types[] = DocumentType::DeveloperStatusLetter;
            }

            foreach ($types as $type) {
                $path = "listings/{$listing->id}/{$type->value}.jpg";
                $disk->put($path, $bytes);
                ListingDocument::query()->updateOrCreate(
                    [
                        'listing_id' => $listing->id,
                        'type' => $type,
                        'version' => 1,
                    ],
                    [
                        'disk' => 'private_documents',
                        'path' => $path,
                        'original_name' => $type->value.'.jpg',
                        'mime' => 'image/jpeg',
                        'size_bytes' => strlen($bytes),
                        'review_status' => $listing->status === ListingStatus::Draft
                            ? DocumentReviewStatus::Pending
                            : DocumentReviewStatus::Approved,
                        'reviewed_at' => $listing->status === ListingStatus::Draft ? null : now()->subDays(3),
                    ],
                );
            }

            if (ListingStatusHistory::query()->where('listing_id', $listing->id)->doesntExist()) {
                $to = $listing->status ?? ListingStatus::Draft;
                ListingStatusHistory::query()->create([
                    'listing_id' => $listing->id,
                    'from_status' => $to === ListingStatus::Draft ? null : ListingStatus::Submitted,
                    'to_status' => $to,
                    'actor_type' => $seller->getMorphClass(),
                    'actor_id' => $seller->id,
                    'reason' => 'demo-seed',
                    'created_at' => now()->subDays(2),
                ]);
            }
        }

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $seller->id],
            [
                'push_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => true,
                'marketing_opt_in' => false,
            ],
        );
    }

    /**
     * @param  list<Listing>  $listings
     * @param  array{areas: array<string, Area>}  $catalog
     */
    private function seedBuyerExperience(User $buyer, array $listings, array $catalog): void
    {
        $public = array_values(array_filter(
            $listings,
            fn (Listing $listing): bool => $listing->status->isPublic(),
        ));

        if ($public === []) {
            return;
        }

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $buyer->id],
            [
                'push_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => true,
                'marketing_opt_in' => true,
            ],
        );

        foreach (array_slice($public, 0, 6) as $listing) {
            Favorite::query()->firstOrCreate([
                'user_id' => $buyer->id,
                'listing_id' => $listing->id,
            ]);
        }

        foreach (array_slice($public, 0, 10) as $index => $listing) {
            ListingView::query()->updateOrCreate(
                [
                    'user_id' => $buyer->id,
                    'listing_id' => $listing->id,
                ],
                [
                    'viewed_at' => now()->subMinutes($index * 7),
                ],
            );
        }

        $newCairo = $catalog['areas']['new-cairo'] ?? null;
        SavedSearch::query()->updateOrCreate(
            [
                'user_id' => $buyer->id,
                'name' => 'القاهرة الجديدة · حتى 5 مليون',
            ],
            [
                'filters' => array_filter([
                    'segment' => MarketSegment::NonDistressed->value,
                    'area_id' => $newCairo?->id,
                    'price_max' => 5000000,
                    'bedrooms' => 3,
                ]),
                'notify' => true,
            ],
        );

        SavedSearch::query()->updateOrCreate(
            [
                'user_id' => $buyer->id,
                'name' => 'فرص متعثرة',
            ],
            [
                'filters' => [
                    'segment' => MarketSegment::Distressed->value,
                    'price_max' => 3500000,
                ],
                'notify' => true,
            ],
        );

        $interestListing = $public[0];
        $lead = Lead::query()->updateOrCreate(
            ['public_code' => $this->demoCode('LD', $buyer, 1)],
            [
                'source' => 'listing_inquiry',
                'buyer_id' => $buyer->id,
                'listing_id' => $interestListing->id,
                'seller_id' => $interestListing->user_id,
                'status' => 'new',
                'priority' => 'medium',
                'next_action' => 'first_call',
                'sla_due_at' => now()->addHours(2),
                'is_duplicate' => false,
            ],
        );

        Inquiry::query()->updateOrCreate(
            ['public_code' => $this->demoCode('INQ', $buyer, 1)],
            [
                'user_id' => $buyer->id,
                'listing_id' => $interestListing->id,
                'lead_id' => $lead->id,
                'message' => 'أرغب في تفاصيل أكثر عن جدولة المعاينة.',
                'inquiry_type' => 'interested',
                'contact_method' => ContactMethod::Phone,
                'preferred_time' => PreferredTime::Evening,
                'consent_at' => now(),
                'status' => InquiryStatus::New,
            ],
        );

        if (isset($public[1])) {
            $second = $public[1];
            $lead2 = Lead::query()->updateOrCreate(
                ['public_code' => $this->demoCode('LD', $buyer, 2)],
                [
                    'source' => 'listing_inquiry',
                    'buyer_id' => $buyer->id,
                    'listing_id' => $second->id,
                    'seller_id' => $second->user_id,
                    'status' => 'contacted',
                    'priority' => 'high',
                    'next_action' => 'schedule_viewing',
                    'sla_due_at' => now()->addHours(1),
                    'is_duplicate' => false,
                ],
            );

            Inquiry::query()->updateOrCreate(
                ['public_code' => $this->demoCode('INQ', $buyer, 2)],
                [
                    'user_id' => $buyer->id,
                    'listing_id' => $second->id,
                    'lead_id' => $lead2->id,
                    'message' => null,
                    'inquiry_type' => 'viewing',
                    'contact_method' => ContactMethod::Phone,
                    'preferred_time' => PreferredTime::Morning,
                    'consent_at' => now(),
                    'status' => InquiryStatus::InReview,
                ],
            );

            Appointment::query()->updateOrCreate(
                ['public_code' => $this->demoCode('APT', $buyer, 1)],
                [
                    'user_id' => $buyer->id,
                    'listing_id' => $second->id,
                    'lead_id' => $lead2->id,
                    'requested_date' => now()->addDays(3)->toDateString(),
                    'requested_slot' => '10:30',
                    'notes' => 'أفضل المعاينة صباحاً.',
                    'status' => AppointmentStatus::Requested,
                    'consent_at' => now(),
                ],
            );

            Appointment::query()->updateOrCreate(
                ['public_code' => $this->demoCode('APT', $buyer, 2)],
                [
                    'user_id' => $buyer->id,
                    'listing_id' => $interestListing->id,
                    'lead_id' => $lead->id,
                    'requested_date' => now()->addDays(6)->toDateString(),
                    'requested_slot' => '16:30',
                    'notes' => null,
                    'status' => AppointmentStatus::Confirmed,
                    'consent_at' => now(),
                ],
            );
        }

        $newCairoArea = $catalog['areas']['new-cairo'] ?? null;
        $apartment = $catalog['unitTypes']['apartment'] ?? null;
        $request = BuyerRequest::query()->updateOrCreate(
            ['public_code' => $this->demoCode('BR', $buyer, 1)],
            [
                'user_id' => $buyer->id,
                'status' => BuyerRequestStatus::OptionsShared,
                'intent' => BuyerIntent::Living,
                'segment_preference' => SegmentPreference::Any,
                'unit_type_id' => $apartment?->id,
                'governorate_id' => $newCairoArea?->governorate_id,
                'area_id' => $newCairoArea?->id,
                'budget_min' => 2000000,
                'budget_max' => 4500000,
                'area_sqm_min' => 100,
                'area_sqm_max' => 180,
                'bedrooms' => 3,
                'contact_method' => ContactMethod::Phone,
                'consent_at' => now(),
                'criteria' => [
                    'governorate_slugs' => ['cairo', 'giza'],
                    'notes' => 'أفضل القاهرة الجديدة أو الشيخ زايد',
                ],
            ],
        );

        foreach (array_slice($public, 0, 3) as $index => $matchListing) {
            RequestMatch::query()->updateOrCreate(
                [
                    'buyer_request_id' => $request->id,
                    'listing_id' => $matchListing->id,
                ],
                [
                    'score' => 90 - ($index * 8),
                    'sent_at' => now()->subHours($index + 1),
                    'buyer_response' => $index === 0 ? MatchBuyerResponse::Interested : MatchBuyerResponse::Waiting,
                ],
            );
        }

        $case = TransferCase::query()->updateOrCreate(
            ['public_code' => $this->demoCode('TR', $buyer, 1)],
            [
                'buyer_id' => $buyer->id,
                'seller_id' => $interestListing->user_id,
                'listing_id' => $interestListing->id,
                'lead_id' => $lead->id,
                'buyer_request_id' => $request->id,
                'internal_stage' => CaseStage::QualifyBuyer,
                'client_label' => CaseStage::QualifyBuyer->clientLabel(),
                'status' => CaseStatus::Open,
                'missing_documents_count' => 1,
                'requested_document_note' => 'يرجى رفع إثبات السداد المحدّث.',
                'last_transition_at' => now()->subDay(),
            ],
        );

        if ($case->history()->doesntExist()) {
            CaseHistory::query()->create([
                'case_id' => $case->id,
                'old_stage' => null,
                'new_stage' => CaseStage::NewClient,
                'old_status' => null,
                'new_status' => null,
                'reason' => 'demo-open',
            ]);
            CaseHistory::query()->create([
                'case_id' => $case->id,
                'old_stage' => CaseStage::NewClient,
                'new_stage' => CaseStage::QualifyBuyer,
                'old_status' => null,
                'new_status' => null,
                'reason' => 'demo-qualify',
            ]);
        }

        $this->seedSupportThread($buyer);

        $matched = $public[min(2, count($public) - 1)];
        $notifications = [
            ['inquiry_created', NotificationCategory::Inquiry, 'تم استلام استفسارك', 'Inquiry received', 'استفسارك عن '.$interestListing->public_code.' وصل لفريق المبيعات.', 'Your inquiry on '.$interestListing->public_code.' was received by the sales team.', ['listing_code' => $interestListing->public_code]],
            ['buyer_request_created', NotificationCategory::Case, 'تم استلام طلبك', 'Request received', 'طلبك '.$request->public_code.' قيد المراجعة.', 'Your request '.$request->public_code.' is in review.', ['public_code' => $request->public_code]],
            ['case_stage_changed', NotificationCategory::Case, 'تحديث مرحلة الحالة', 'Case update', 'حالة '.$case->public_code.': تم تأهيل المشتري', 'Case '.$case->public_code.': buyer qualified', ['public_code' => $case->public_code, 'client_label' => 'buyer_qualified']],
            ['smart_saved_search', NotificationCategory::Smart, 'عقار يطابق بحثك', 'A listing matches your search', 'عقار '.$matched->public_code.' يطابق بحثك المحفوظ.', 'Listing '.$matched->public_code.' matches your saved search.', ['listing_code' => $matched->public_code]],
            ['marketing_broadcast', NotificationCategory::Marketing, 'وحدات جديدة هذا الأسبوع', 'New units this week', 'تصفح أحدث فرص الخروج المنشورة على عَقدار.', 'Browse the latest exit opportunities published on AQDAR.', []],
            ['support_reply', NotificationCategory::Inquiry, 'رد من المبيعات', 'Sales replied', 'فريق المبيعات رد على محادثة الدعم.', 'Sales replied on your support thread.', []],
        ];

        foreach ($notifications as $index => [$eventKey, $category, $titleAr, $titleEn, $bodyAr, $bodyEn, $data]) {
            InboxNotification::query()->updateOrCreate(
                [
                    'idempotency_key' => 'demo:'.$eventKey.':'.$buyer->id,
                ],
                [
                    'notifiable_type' => $buyer->getMorphClass(),
                    'notifiable_id' => $buyer->id,
                    'event_key' => $eventKey,
                    'template_key' => $eventKey,
                    'category' => $category,
                    'title_ar' => $titleAr,
                    'title_en' => $titleEn,
                    'body_ar' => $bodyAr,
                    'body_en' => $bodyEn,
                    'data' => $data,
                    'read_at' => $index === 0 ? now() : null,
                ],
            );
        }
    }

    private function seedSupportThread(User $buyer): void
    {
        $staff = StaffUser::query()->orderBy('id')->first();
        $thread = SupportThread::query()->updateOrCreate(
            ['public_code' => $this->demoCode('SUP', $buyer, 1)],
            [
                'user_id' => $buyer->id,
                'assigned_to' => $staff?->id,
                'status' => SupportThreadStatus::Open,
                'consent_at' => now(),
            ],
        );

        if ($thread->messages()->exists()) {
            return;
        }

        SupportMessage::query()->create([
            'thread_id' => $thread->id,
            'author_type' => SupportAuthorType::User,
            'user_id' => $buyer->id,
            'staff_id' => null,
            'body' => 'مرحباً، أحتاج مساعدة في متابعة حالة التنازل.',
            'created_at' => now()->subHours(5),
        ]);

        SupportMessage::query()->create([
            'thread_id' => $thread->id,
            'author_type' => SupportAuthorType::Staff,
            'user_id' => null,
            'staff_id' => $staff?->id,
            'body' => 'أهلاً بك. الحالة قيد التأهيل وسنتواصل عند اكتمال المستندات.',
            'created_at' => now()->subHours(4),
        ]);
    }

    private function ensureSalesPhone(): void
    {
        $existing = Setting::query()->where('key', 'sales_phone')->first();
        if ($existing !== null && filled($existing->value)) {
            return;
        }

        Setting::query()->updateOrCreate(
            ['key' => 'sales_phone'],
            ['value' => '+201000000001', 'group' => 'sales'],
        );
    }

    private function demoCode(string $prefix, User $user, int $sequence): string
    {
        return $prefix.'-DEMO-'.$user->id.'-'.$sequence;
    }
}
