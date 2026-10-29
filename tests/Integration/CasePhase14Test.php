<?php

namespace Tests\Integration;

use App\Core\Enums\CaseStage;
use App\Core\Enums\CaseStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\CaseDocument;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Crm\Services\CaseService;
use App\Modules\Listing\Models\Listing;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class CasePhase14Test extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_seller_can_list_and_open_own_case_without_buyer_pii(): void
    {
        $case = TransferCase::factory()->create();
        $seller = $case->seller;
        $buyer = $case->buyer;
        $this->assertNotNull($seller);
        $this->assertNotNull($buyer);

        Sanctum::actingAs($seller);
        $this->getJson('/api/v1/my/cases')
            ->assertOk()
            ->assertJsonPath('data.0.id', $case->id)
            ->assertJsonPath('data.0.viewer', 'seller')
            ->assertJsonPath('data.0.lifecycle', 'open')
            ->assertJsonPath('data.0.status', 'interest_received')
            ->assertJsonMissingPath('data.0.internal_stage')
            ->assertJsonMissingPath('data.0.buyer')
            ->assertJsonMissingPath('data.0.seller');

        $show = $this->getJson('/api/v1/my/cases/'.$case->id)
            ->assertOk()
            ->assertJsonPath('data.viewer', 'seller')
            ->assertJsonMissingPath('data.internal_stage')
            ->assertJsonMissingPath('data.internal_note');
        $encoded = (string) json_encode($show->json());
        $this->assertStringNotContainsString((string) $buyer->name, $encoded);
        $this->assertStringNotContainsString((string) $buyer->phone, $encoded);
        $this->assertStringNotContainsString((string) $buyer->email, $encoded);
        $this->assertStringNotContainsString('new_client', $encoded);

        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/my/cases/'.$case->id)
            ->assertOk()
            ->assertJsonPath('data.viewer', 'buyer');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/my/cases/'.$case->id)->assertNotFound();
        $this->getJson('/api/v1/my/cases')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_hold_and_cancel_write_case_history_with_old_and_new_status(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Operations);
        $case = TransferCase::factory()->create(['status' => CaseStatus::Open]);

        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/lifecycle', [
                'status' => 'on_hold',
                'reason' => 'بانتظار مستند من الأطراف',
            ])->assertRedirect();
        $this->assertSame(CaseStatus::OnHold, $case->fresh()->status);
        $this->assertDatabaseHas('case_history', [
            'case_id' => $case->id,
            'actor_id' => $staff->id,
            'old_status' => 'open',
            'new_status' => 'on_hold',
            'reason' => 'بانتظار مستند من الأطراف',
        ]);

        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/lifecycle', [
                'status' => 'cancelled',
                'reason' => 'ألغى المشتري المتابعة',
            ])->assertRedirect();
        $this->assertSame(CaseStatus::Cancelled, $case->fresh()->status);
        $this->assertDatabaseHas('case_history', [
            'case_id' => $case->id,
            'old_status' => 'on_hold',
            'new_status' => 'cancelled',
        ]);

        $this->get('/dashboard/cases/'.$case->id)
            ->assertOk()
            ->assertSee(__('aqdar.cases.lifecycle.on_hold'), false)
            ->assertSee(__('aqdar.cases.lifecycle.cancelled'), false)
            ->assertSee('بانتظار مستند من الأطراف', false);
    }

    public function test_waiting_for_information_is_a_lifecycle_status(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $case = TransferCase::factory()->create();

        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/lifecycle', [
                'status' => 'waiting_for_information',
                'reason' => 'نحتاج إيصال القسط',
            ])->assertRedirect();

        $this->assertSame(CaseStatus::WaitingForInformation, $case->fresh()->status);

        $this->get('/dashboard/cases')
            ->assertOk()
            ->assertSee(__('aqdar.cases.lifecycle_column'), false)
            ->assertSee(__('aqdar.cases.lifecycle.waiting_for_information'), false);

        Sanctum::actingAs($case->seller);
        $this->getJson('/api/v1/my/cases/'.$case->id)
            ->assertOk()
            ->assertJsonPath('data.lifecycle', 'waiting_for_information')
            ->assertJsonMissingPath('data.internal_stage');
    }

    public function test_stage_change_notifies_buyer_and_seller_without_pii(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $case = TransferCase::factory()->create();
        $buyer = $case->buyer;
        $seller = $case->seller;

        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/stage', [
                'stage' => CaseStage::QualifyBuyer->value,
                'reason' => 'تم التأهيل',
            ])->assertRedirect();

        $this->assertSame(2, InboxNotification::query()->where('event_key', 'case_stage_changed')->count());
        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'case_stage_changed')
                ->where('notifiable_id', $buyer->id)
                ->exists()
        );
        $this->assertTrue(
            InboxNotification::query()
                ->where('event_key', 'case_stage_changed')
                ->where('notifiable_id', $seller->id)
                ->exists()
        );

        $blob = InboxNotification::query()
            ->where('event_key', 'case_stage_changed')
            ->get()
            ->map(fn ($row) => $row->title_ar.$row->body_ar.$row->body_en.json_encode($row->data))
            ->implode(' ');
        $this->assertStringNotContainsString((string) $buyer->name, $blob);
        $this->assertStringNotContainsString((string) $buyer->phone, $blob);
        $this->assertStringNotContainsString((string) $seller->phone, $blob);
        $this->assertStringNotContainsString('qualify_buyer', strtolower($blob));
        $this->assertStringContainsString(__('aqdar.cases.client.buyer_qualified'), $blob);
    }

    public function test_seller_inbox_includes_own_case(): void
    {
        $case = TransferCase::factory()->create();
        Sanctum::actingAs($case->seller);

        $row = collect($this->getJson('/api/v1/my/inquiries')->assertOk()->json('data'))
            ->firstWhere('kind', 'case');

        $this->assertNotNull($row);
        $this->assertSame($case->id, $row['id']);
        $this->assertSame($case->client_label, $row['label']);
    }

    public function test_timeline_dates_come_from_case_history(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Operations);
        $buyer = User::factory()->create();
        $listing = Listing::factory()->published()->create();
        $case = app(CaseService::class)->open([
            'buyer_id' => $buyer->id,
            'seller_id' => $listing->user_id,
            'listing_id' => $listing->id,
        ], $staff, 'فتح الحالة');

        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/stage', [
                'stage' => CaseStage::QualifyBuyer->value,
                'reason' => 'تم التأهيل',
            ])->assertRedirect();

        Sanctum::actingAs($case->fresh()->seller);
        $timeline = collect($this->getJson('/api/v1/my/cases/'.$case->id)->assertOk()->json('data.timeline'));
        $received = $timeline->firstWhere('key', 'interest_received');
        $qualified = $timeline->firstWhere('key', 'buyer_qualified');
        $this->assertNotNull($received['at']);
        $this->assertNotNull($qualified['at']);
        $this->assertSame('done', $received['state']);
        $this->assertSame('current', $qualified['state']);
    }

    public function test_lost_writes_lifecycle_history_and_notifies_both_parties(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $case = TransferCase::factory()->create();
        $buyer = $case->buyer;
        $seller = $case->seller;

        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/lifecycle', [
                'status' => 'lost',
                'reason' => 'انسحب المشتري',
            ])->assertRedirect();

        $this->assertSame(CaseStatus::Lost, $case->fresh()->status);
        $this->assertDatabaseHas('case_history', [
            'case_id' => $case->id,
            'old_status' => 'open',
            'new_status' => 'lost',
            'reason' => 'انسحب المشتري',
        ]);
        $this->assertSame(2, InboxNotification::query()->where('event_key', 'case_stage_changed')->count());
        $this->assertTrue(
            InboxNotification::query()->where('event_key', 'case_stage_changed')->where('notifiable_id', $buyer->id)->exists()
        );
        $this->assertTrue(
            InboxNotification::query()->where('event_key', 'case_stage_changed')->where('notifiable_id', $seller->id)->exists()
        );
        $blob = InboxNotification::query()
            ->where('event_key', 'case_stage_changed')
            ->get()
            ->map(fn ($row) => $row->title_ar.$row->body_ar.$row->body_en.json_encode($row->data))
            ->implode(' ');
        $this->assertStringContainsString(__('aqdar.cases.lifecycle.lost'), $blob);
        $this->assertStringNotContainsString((string) $buyer->phone, $blob);
        $this->assertStringNotContainsString((string) $seller->phone, $blob);
    }

    public function test_completing_via_stage_writes_status_history(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $case = TransferCase::factory()->create(['status' => CaseStatus::Open]);

        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/stage', [
                'stage' => CaseStage::Completed->value,
                'reason' => 'اكتملت الصفقة',
            ])->assertRedirect();

        $this->assertSame(CaseStatus::Completed, $case->fresh()->status);
        $this->assertDatabaseHas('case_history', [
            'case_id' => $case->id,
            'old_status' => 'open',
            'new_status' => 'completed',
            'reason' => 'اكتملت الصفقة',
        ]);
    }

    public function test_seller_can_upload_requested_case_document(): void
    {
        Storage::fake('private_documents');
        $case = TransferCase::factory()->create([
            'missing_documents_count' => 1,
            'requested_document_note' => 'إيصال القسط',
        ]);
        Sanctum::actingAs($case->seller);

        $this->post('/api/v1/my/cases/'.$case->id.'/documents', [
            'kind' => 'buyer_requested',
            'file' => UploadedFile::fake()->create('seller-receipt.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(0, (int) $case->fresh()->missing_documents_count);
        $this->assertNull($case->fresh()->requested_document_note);
        $this->assertTrue(
            CaseDocument::query()->where('case_id', $case->id)->where('uploaded_by_user_id', $case->seller_id)->exists()
        );
    }
}
