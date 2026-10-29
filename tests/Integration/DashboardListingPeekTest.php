<?php

namespace Tests\Integration;

use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Listing\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardListingPeekTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_reviewer_can_open_listing_desk_and_cannot_publish(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create([
            'status' => ListingStatus::InReview,
            'title_ar' => 'شقة للمراجعة',
            'title_en' => 'Review apartment',
            'public_code' => 'AQ-9901',
            'contact_flagged' => true,
            'contact_flag_reason' => 'phone',
        ]);

        $this->get('/dashboard/listings')
            ->assertOk()
            ->assertSee(__('aqdar.menu.adm_02'))
            ->assertSee('AQ-9901', false)
            ->assertSee('Review apartment', false)
            ->assertSee(__('aqdar.listings.desk.approve_publish_note'))
            ->assertSee(route('dashboard.listings.show', $listing), false);

        $this->get('/dashboard/listings/'.$listing->id)
            ->assertOk()
            ->assertSee('AQ-9901', false)
            ->assertSee(__('aqdar.listings.desk.declared'))
            ->assertSee(__('aqdar.listings.desk.verified'))
            ->assertSee(__('aqdar.listings.desk.pending_verify'))
            ->assertSee(__('aqdar.listings.desk.approve_publish_note'))
            ->assertSee(route('dashboard.listings.approve', $listing), false);

        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'نشر'])
            ->assertForbidden();
    }

    public function test_sales_and_finance_cannot_open_listing_desk(): void
    {
        $listing = Listing::factory()->create();

        $this->actingAsStaff(StaffRole::Sales);
        $this->get('/dashboard/listings')->assertForbidden();
        $this->get('/dashboard/listings/'.$listing->id)->assertForbidden();

        $this->actingAsStaff(StaffRole::Finance);
        $this->get('/dashboard/listings')->assertForbidden();
    }

    public function test_operations_can_filter_queue_tabs_and_search(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $review = Listing::factory()->create([
            'status' => ListingStatus::InReview,
            'title_ar' => 'وحدة قيد المراجعة',
            'public_code' => 'AQ-8801',
        ]);
        $draft = Listing::factory()->create([
            'status' => ListingStatus::Draft,
            'title_ar' => 'مسودة خاصة',
            'public_code' => 'AQ-8802',
        ]);

        $this->get('/dashboard/listings?status=in_review')
            ->assertOk()
            ->assertSee('AQ-8801', false)
            ->assertDontSee('AQ-8802', false);

        $this->get('/dashboard/listings?q=AQ-8802')
            ->assertOk()
            ->assertSee('AQ-8802', false)
            ->assertDontSee('AQ-8801', false);

        $this->assertNotNull($review->id);
        $this->assertNotNull($draft->id);
    }

    public function test_queue_search_includes_developer_and_tabs_keep_filters(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $developer = Developer::factory()->create([
            'name_ar' => 'ماونتن فيو تجريبي',
            'name_en' => 'Mountain View TestCo',
        ]);
        $hit = Listing::factory()->create([
            'status' => ListingStatus::InReview,
            'developer_id' => $developer->id,
            'segment' => \App\Core\Enums\MarketSegment::Distressed,
            'public_code' => 'AQ-DEV9',
        ]);
        Listing::factory()->create([
            'status' => ListingStatus::InReview,
            'public_code' => 'AQ-NODEV',
        ]);

        $this->get('/dashboard/listings?q=Mountain+View+TestCo')
            ->assertOk()
            ->assertSee('AQ-DEV9', false)
            ->assertDontSee('AQ-NODEV', false);

        $html = $this->get('/dashboard/listings?segment=distressed&alerts=1')->assertOk()->getContent();
        $this->assertStringContainsString('segment=distressed', $html);
        $this->assertStringContainsString('alerts=1', $html);
        $this->assertNotNull($hit->id);
    }

    public function test_adm_01_uses_real_listing_counts(): void
    {
        Listing::factory()->count(2)->create(['status' => ListingStatus::InReview]);
        Listing::factory()->published()->create();

        $stats = app(DashboardService::class)->stats();

        $this->assertSame(3, $stats['total_listings']);
        $this->assertSame(2, $stats['listings_in_review']);
        $this->assertSame(1, $stats['listings_published']);

        $this->actingAsStaff(StaffRole::SuperAdmin);
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee((string) $stats['total_listings'])
            ->assertSee(route('dashboard.listings.index'), false);
    }
}
