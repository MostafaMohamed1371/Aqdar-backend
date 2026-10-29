<?php

namespace Tests\Integration;

use App\Core\Audit\Models\AccessLog;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\MediaModerationStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use App\Modules\Listing\Models\ListingMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesStaffUsers;
use Tests\Concerns\UploadsListingDocuments;
use Tests\TestCase;

class DashboardModerationTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;
    use UploadsListingDocuments;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_documents');
        Storage::fake('private_media');
        Storage::fake('public_media');
    }

    public function test_reviewer_can_approve_but_cannot_publish(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);
        $this->seedApprovedRequiredDocuments($listing);

        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/approve', ['reason' => 'المستندات مكتملة'])
            ->assertRedirect();
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);

        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'نشر'])
            ->assertForbidden();
        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
    }

    public function test_operations_can_publish_and_hold_with_reason(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $listing = Listing::factory()->create(['status' => ListingStatus::Approved]);
        $this->seedApprovedRequiredDocuments($listing);

        $this->post('/dashboard/listings/'.$listing->id.'/publish', ['reason' => 'جاهز للسوق'])
            ->assertRedirect();
        $this->assertSame(ListingStatus::Published, $listing->fresh()->status);
        $this->assertNotNull($listing->fresh()->published_at);

        $this->post('/dashboard/listings/'.$listing->id.'/hold', ['reason' => 'شكوى مؤقتة'])
            ->assertRedirect();
        $this->assertSame(ListingStatus::Hold, $listing->fresh()->status);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
    }

    public function test_verified_financials_do_not_overwrite_declared(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);
        $listing->financials()->update([
            'declared_asking_price' => 900000,
            'verified_asking_price' => null,
            'asking_price_verified' => false,
        ]);

        $this->post('/dashboard/listings/'.$listing->id.'/verify-financials', [
            'verified_asking_price' => 850000,
        ])->assertRedirect();

        $financials = $listing->fresh()->financials;
        $this->assertEquals(900000, (float) $financials->declared_asking_price);
        $this->assertEquals(850000, (float) $financials->verified_asking_price);
        $this->assertTrue($financials->asking_price_verified);
    }

    public function test_sales_cannot_open_private_documents_and_attempt_is_logged(): void
    {
        $listing = Listing::factory()->create();
        $document = ListingDocument::factory()->create(['listing_id' => $listing->id]);
        $this->actingAsStaff(StaffRole::Sales);

        $this->get('/dashboard/documents')->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'permission_denied']);

        $url = URL::temporarySignedRoute('dashboard.documents.download', now()->addMinutes(10), ['document' => $document]);
        $this->get($url)->assertForbidden();
    }

    public function test_reviewer_signed_download_is_logged(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create();
        Storage::disk('private_documents')->put('listings/1/id.pdf', '%PDF-1.4 test');
        $document = ListingDocument::factory()->create([
            'listing_id' => $listing->id,
            'path' => 'listings/1/id.pdf',
            'mime' => 'application/pdf',
        ]);

        $url = URL::temporarySignedRoute('dashboard.documents.download', now()->addMinutes(10), ['document' => $document]);
        $this->get($url)->assertOk();
        $this->assertSame(1, AccessLog::query()->where('action', 'document.opened')->count());
    }

    public function test_held_media_is_not_public_until_staff_approves(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->published()->create();
        Storage::disk('private_media')->put('listings/1/orig.jpg', 'bytes');
        $media = ListingMedia::factory()->create([
            'listing_id' => $listing->id,
            'original_path' => 'listings/1/orig.jpg',
            'moderation_status' => MediaModerationStatus::Held,
            'hold_reason' => 'phone',
        ]);

        $this->post('/dashboard/listings/'.$listing->id.'/media/'.$media->id.'/approve')->assertRedirect();
        $this->assertNotNull($media->fresh()->public_path);
        $this->getJson('/api/v1/listings/'.$listing->id.'/media')
            ->assertOk()
            ->assertJsonPath('data.0.id', $media->id);
    }

    public function test_reviewer_can_request_changes_reject_and_reopen(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);

        $this->post('/dashboard/listings/'.$listing->id.'/request-changes', ['reason' => 'الصور غير واضحة'])
            ->assertRedirect();
        $this->assertSame(ListingStatus::NeedsChanges, $listing->fresh()->status);

        $this->post('/dashboard/listings/'.$listing->id.'/reject', ['reason' => 'مستندات غير مكتملة'])
            ->assertRedirect();
        $this->assertSame(ListingStatus::Rejected, $listing->fresh()->status);

        $this->post('/dashboard/listings/'.$listing->id.'/reopen', ['reason' => 'إعادة النظر بعد تصحيح'])
            ->assertRedirect();
        $this->assertSame(ListingStatus::InReview, $listing->fresh()->status);
    }

    public function test_operations_can_close_published_listing(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $listing = Listing::factory()->published()->create();

        $this->post('/dashboard/listings/'.$listing->id.'/close', ['reason' => 'تم البيع خارج المنصة'])
            ->assertRedirect();
        $this->assertSame(ListingStatus::Closed, $listing->fresh()->status);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
    }

    public function test_seller_recovery_verified_flag_uses_existing_column(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);
        $listing->financials()->update([
            'declared_seller_recovery_amount' => 400000,
            'verified_seller_recovery_amount' => null,
            'seller_recovery_verified' => false,
        ]);

        $this->post('/dashboard/listings/'.$listing->id.'/verify-financials', [
            'verified_seller_recovery_amount' => 390000,
        ])->assertRedirect();

        $financials = $listing->fresh()->financials;
        $this->assertEquals(400000, (float) $financials->declared_seller_recovery_amount);
        $this->assertEquals(390000, (float) $financials->verified_seller_recovery_amount);
        $this->assertTrue($financials->seller_recovery_verified);
    }

    public function test_rejecting_approved_media_removes_public_file(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->published()->create();
        Storage::disk('private_media')->put('listings/'.$listing->id.'/orig.jpg', 'bytes');
        $media = ListingMedia::factory()->create([
            'listing_id' => $listing->id,
            'original_path' => 'listings/'.$listing->id.'/orig.jpg',
            'moderation_status' => MediaModerationStatus::Held,
            'hold_reason' => 'phone',
        ]);

        $this->post('/dashboard/listings/'.$listing->id.'/media/'.$media->id.'/approve')->assertRedirect();
        $path = $media->fresh()->public_path;
        $this->assertNotNull($path);
        $this->assertTrue(Storage::disk('public_media')->exists($path));

        $this->post('/dashboard/listings/'.$listing->id.'/media/'.$media->id.'/reject', ['reason' => 'صورة غير واضحة'])
            ->assertRedirect();
        $this->assertNull($media->fresh()->public_path);
        $this->assertFalse(Storage::disk('public_media')->exists($path));
        $this->getJson('/api/v1/listings/'.$listing->id.'/media')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
