<?php

namespace Tests\Integration;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Listing\Models\Listing;
use App\Modules\Notification\Database\Seeders\NotificationSeeder;
use App\Modules\Staff\Database\Seeders\StaffSeeder;
use App\Modules\User\Database\Seeders\UserSeeder;
use App\Modules\User\Models\User;
use Database\Seeders\MobileDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileDemoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_documents');
        Storage::fake('private_media');
        Storage::fake('public_media');

        $this->seed([
            StaffSeeder::class,
            UserSeeder::class,
            CatalogSeeder::class,
            NotificationSeeder::class,
            MobileDemoSeeder::class,
        ]);
    }

    public function test_seeded_public_catalog_and_listings_are_populated(): void
    {
        $this->getJson('/api/v1/config')->assertOk()->assertJsonPath('data.currency', 'EGP');
        $this->getJson('/api/v1/categories')->assertOk();
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/locations')->json('data')));
        $this->assertNotEmpty($this->getJson('/api/v1/developers')->json('data.0.logo_url'));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/projects')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/unit-types')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/amenities')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/tags')->json('data')));
        $this->assertGreaterThan(1, count($this->getJson('/api/v1/content/banners')->json('data')));
        $this->assertNotEmpty($this->getJson('/api/v1/content/banners')->json('data.0.image_url'));
        $this->assertGreaterThan(1, count($this->getJson('/api/v1/content/faqs')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/content/onboarding')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/content/seller-guide')->json('data')));
        $this->assertGreaterThan(0, $this->getJson('/api/v1/listings')->json('meta.total'));
        $this->assertNotEmpty($this->getJson('/api/v1/listings')->json('data.0.photos.0.url'));
        $this->getJson('/api/v1/listings/counts')->assertOk();
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/map')->json('data')));
    }

    public function test_seeded_buyer_account_has_data_on_personal_apis(): void
    {
        $buyer = User::query()->where('email', 'test@example.com')->firstOrFail();
        Sanctum::actingAs($buyer);

        $listingId = Listing::query()->public()->orderBy('id')->value('id');
        $this->assertNotNull($listingId);

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.phone', '+201012345678');
        $this->getJson('/api/v1/me/preferences')->assertOk()->assertJsonPath('data.push_enabled', true);
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/favorites')->json('data')));
        $this->assertNotEmpty($this->getJson('/api/v1/favorites')->json('data.0.photos.0.url'));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/recently-viewed')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/notifications')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/saved-searches')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/my/inquiries')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/my/buyer-requests')->json('data')));
        $this->assertNotEmpty($this->getJson('/api/v1/my/buyer-requests')->json('data.0.options.0.listing.id'));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/my/cases')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/my/appointments')->json('data')));
        $this->assertNotEmpty($this->getJson('/api/v1/my/support')->json('data.messages.0.body'));
        $this->getJson('/api/v1/appointments/slots?date='.now()->addDays(3)->toDateString())->assertOk();
        $this->getJson('/api/v1/listings/'.$listingId.'/estimate')->assertOk();
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/listings/'.$listingId.'/media')->json('data')));
    }

    public function test_seeded_seller_account_has_listings_documents_and_interest(): void
    {
        $seller = User::query()->where('email', 'seller@example.com')->firstOrFail();
        Sanctum::actingAs($seller);

        $this->assertGreaterThan(0, count($this->getJson('/api/v1/my/listings')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/my/documents')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/my/interest')->json('data')));
        $this->assertGreaterThan(0, count($this->getJson('/api/v1/my/cases')->json('data')));
    }
}
