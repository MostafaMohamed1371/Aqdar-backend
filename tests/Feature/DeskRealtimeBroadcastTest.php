<?php

namespace Tests\Feature;

use App\Core\Enums\DocumentType;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Dashboard\Events\DeskUpdated;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Services\ListingFileService;
use App\Modules\Listing\Services\ListingService;
use App\Modules\Notification\Events\StaffInboxReceived;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesStaffUsers;
use Tests\Concerns\UploadsListingDocuments;
use Tests\TestCase;

class DeskRealtimeBroadcastTest extends TestCase
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

    public function test_mobile_listing_submit_broadcasts_desk_and_staff_inbox_events(): void
    {
        $this->makeStaff(StaffRole::Operations);
        $seller = User::factory()->seller()->create();
        $draft = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Draft,
        ]);
        $this->seedRequiredDocuments($draft);

        Event::fake([DeskUpdated::class, StaffInboxReceived::class]);

        app(ListingService::class)->submit($draft->fresh(['financials', 'category']), $seller, true);

        Event::assertDispatched(DeskUpdated::class, function (DeskUpdated $event): bool {
            return $event->resource === 'listings'
                && $event->action === 'submitted'
                && $event->id !== null;
        });
        Event::assertDispatched(StaffInboxReceived::class);
    }

    public function test_mobile_document_upload_broadcasts_desk_waiting_action(): void
    {
        $this->makeStaff(StaffRole::Operations);
        $seller = User::factory()->seller()->create();
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Draft,
        ]);

        Event::fake([DeskUpdated::class, StaffInboxReceived::class]);

        app(ListingFileService::class)->storeDocument(
            $listing,
            $seller,
            DocumentType::NationalId,
            UploadedFile::fake()->create('id.pdf', 120, 'application/pdf'),
        );

        Event::assertDispatched(DeskUpdated::class, function (DeskUpdated $event): bool {
            return $event->resource === 'documents'
                && $event->action === 'document_uploaded'
                && $event->id !== null;
        });
        Event::assertDispatched(StaffInboxReceived::class);
    }

    public function test_mobile_media_upload_broadcasts_desk_waiting_action(): void
    {
        $this->makeStaff(StaffRole::Operations);
        $seller = User::factory()->seller()->create();
        $listing = Listing::factory()->create([
            'user_id' => $seller->id,
            'status' => ListingStatus::Draft,
        ]);

        Event::fake([DeskUpdated::class, StaffInboxReceived::class]);

        app(ListingFileService::class)->storeMedia(
            $listing,
            $seller,
            UploadedFile::fake()->image('unit.jpg', 800, 600),
        );

        Event::assertDispatched(DeskUpdated::class, function (DeskUpdated $event): bool {
            return $event->resource === 'media'
                && $event->action === 'uploaded'
                && $event->id !== null;
        });
        Event::assertDispatched(StaffInboxReceived::class);
    }
}
