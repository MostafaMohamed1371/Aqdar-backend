<?php

namespace Tests\Integration;

use App\Core\Audit\Models\AccessLog;
use App\Core\Audit\Models\AuditLog;
use App\Core\Enums\BuyerRequestStatus;
use App\Core\Enums\CaseStage;
use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\CaseDocument;
use App\Modules\Crm\Models\RequestMatch;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Listing\Services\PdfWatermarker;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardCaseTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_documents');
    }

    public function test_operations_can_open_request_and_case_desks(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $request = BuyerRequest::factory()->create(['public_code' => 'BR-2026-00042']);
        $case = TransferCase::factory()->create(['public_code' => 'TR-2026-00042']);

        $this->get('/dashboard/requests')
            ->assertOk()
            ->assertSee(__('aqdar.menu.adm_08'))
            ->assertSee('BR-2026-00042', false)
            ->assertSee('#add-request', false);

        $this->get('/dashboard/requests/'.$request->id)->assertOk()->assertSee('BR-2026-00042', false);

        $this->get('/dashboard/cases')
            ->assertOk()
            ->assertSee(__('aqdar.menu.adm_09'))
            ->assertSee('TR-2026-00042', false)
            ->assertSee('#add-case', false)
            ->assertSee(__('aqdar.cases.internal_stage'))
            ->assertSee(__('aqdar.cases.lifecycle_column'))
            ->assertSee(__('aqdar.cases.client_sees'));

        $this->get('/dashboard/cases/'.$case->id)
            ->assertOk()
            ->assertSee('TR-2026-00042', false)
            ->assertSee(__('aqdar.cases.upload_letter'));
    }

    public function test_reviewer_and_finance_cannot_open_request_or_case_desks(): void
    {
        $request = BuyerRequest::factory()->create();
        $case = TransferCase::factory()->create();

        $this->actingAsStaff(StaffRole::Reviewer);
        $this->get('/dashboard/requests')->assertForbidden();
        $this->get('/dashboard/cases')->assertForbidden();
        $this->get('/dashboard/requests/'.$request->id)->assertForbidden();
        $this->get('/dashboard/cases/'.$case->id)->assertForbidden();

        $this->actingAsStaff(StaffRole::Finance);
        $this->get('/dashboard/requests')->assertForbidden();
        $this->get('/dashboard/cases')->assertOk()->assertSee($case->public_code, false);
        $this->get('/dashboard/cases/'.$case->id)->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'permission_denied']);
    }

    public function test_sales_sees_only_assigned_requests_and_cases(): void
    {
        $sales = $this->actingAsStaff(StaffRole::Sales);
        $mineRequest = BuyerRequest::factory()->create([
            'assigned_to' => $sales->id,
            'public_code' => 'BR-MINE-1',
        ]);
        BuyerRequest::factory()->create(['public_code' => 'BR-OTHER-1']);
        $mineCase = TransferCase::factory()->create([
            'assigned_to' => $sales->id,
            'public_code' => 'TR-MINE-1',
        ]);
        $otherCase = TransferCase::factory()->create(['public_code' => 'TR-OTHER-1']);

        $this->get('/dashboard/requests')
            ->assertOk()
            ->assertSee('BR-MINE-1', false)
            ->assertDontSee('BR-OTHER-1', false);
        $this->get('/dashboard/requests/'.$mineRequest->id)->assertOk();

        $this->get('/dashboard/cases')
            ->assertOk()
            ->assertSee('TR-MINE-1', false)
            ->assertDontSee('TR-OTHER-1', false);
        $this->get('/dashboard/cases/'.$mineCase->id)->assertOk();
        $this->get('/dashboard/cases/'.$otherCase->id)->assertForbidden();
    }

    public function test_assigned_sales_can_advance_stage_and_history_is_written(): void
    {
        $sales = $this->actingAsStaff(StaffRole::Sales);
        $case = TransferCase::factory()->create(['assigned_to' => $sales->id]);

        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/stage', [
                'stage' => 'qualify_buyer',
                'reason' => 'تم تأهيل المشتري',
                'internal_note' => 'ملاحظة داخلية',
            ])->assertRedirect();

        $fresh = $case->fresh();
        $this->assertSame(CaseStage::QualifyBuyer, $fresh->internal_stage);
        $this->assertSame('buyer_qualified', $fresh->client_label);
        $this->assertDatabaseHas('case_history', [
            'case_id' => $case->id,
            'new_stage' => 'qualify_buyer',
            'reason' => 'تم تأهيل المشتري',
            'internal_note' => 'ملاحظة داخلية',
        ]);
        $this->assertTrue(AuditLog::query()->where('action', 'case.stage')->exists());
    }

    public function test_unassigned_sales_cannot_advance_another_case(): void
    {
        $this->actingAsStaff(StaffRole::Sales);
        $case = TransferCase::factory()->create();

        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/stage', [
                'stage' => 'qualify_buyer',
                'reason' => 'محاولة غير مصرح بها',
            ])->assertForbidden();
    }

    public function test_operations_can_send_match_and_convert_request_to_case(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $request = BuyerRequest::factory()->create(['status' => BuyerRequestStatus::Matching]);
        $match = RequestMatch::factory()->create([
            'buyer_request_id' => $request->id,
            'score' => 88,
        ]);

        $this->from('/dashboard/requests/'.$request->id)
            ->post('/dashboard/requests/'.$request->id.'/matches/'.$match->id.'/send')
            ->assertRedirect();
        $this->assertNotNull($match->fresh()->sent_at);
        $this->assertSame(BuyerRequestStatus::OptionsShared, $request->fresh()->status);

        $this->from('/dashboard/requests/'.$request->id)
            ->post('/dashboard/requests/'.$request->id.'/convert', [
                'reason' => 'العميل اختار عقاراً',
            ])->assertRedirect();

        $this->assertSame(BuyerRequestStatus::Converted, $request->fresh()->status);
        $case = TransferCase::query()->where('buyer_request_id', $request->id)->first();
        $this->assertNotNull($case);
        $this->assertStringStartsWith('TR-', (string) $case->public_code);
    }

    public function test_each_confirmed_listing_from_one_request_gets_its_own_case(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $request = BuyerRequest::factory()->create(['status' => BuyerRequestStatus::OptionsShared]);
        $first = RequestMatch::factory()->create(['buyer_request_id' => $request->id, 'sent_at' => now()]);
        $second = RequestMatch::factory()->create(['buyer_request_id' => $request->id, 'sent_at' => now()]);

        foreach ([$first, $second, $first] as $match) {
            $this->from('/dashboard/requests/'.$request->id)
                ->post('/dashboard/requests/'.$request->id.'/convert', [
                    'reason' => 'صفقة مؤكدة',
                    'match_id' => $match->id,
                ])->assertRedirect();
        }

        $cases = TransferCase::query()->where('buyer_request_id', $request->id)->get();
        $this->assertCount(2, $cases);
        $this->assertEqualsCanonicalizing(
            [$first->listing_id, $second->listing_id],
            $cases->pluck('listing_id')->all(),
        );
        $this->assertSame(BuyerRequestStatus::Converted, $request->fresh()->status);
    }

    public function test_match_from_another_request_cannot_be_converted(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $request = BuyerRequest::factory()->create();
        $foreign = RequestMatch::factory()->create(['sent_at' => now()]);

        $this->post('/dashboard/requests/'.$request->id.'/convert', [
            'reason' => 'محاولة خاطئة',
            'match_id' => $foreign->id,
        ])->assertNotFound();
    }

    public function test_sales_cannot_assign_requests(): void
    {
        $sales = $this->actingAsStaff(StaffRole::Sales);
        $other = $this->makeStaff(StaffRole::Sales, ['email' => 'other-req@aqdar.test']);
        $request = BuyerRequest::factory()->create(['assigned_to' => $sales->id]);

        $this->from('/dashboard/requests/'.$request->id)
            ->post('/dashboard/requests/'.$request->id.'/assign', ['assigned_to' => $other->id])
            ->assertForbidden();
    }

    public function test_unsigned_case_document_download_is_forbidden(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $case = TransferCase::factory()->create();
        Storage::disk('private_documents')->put('cases/1/letter.pdf', '%PDF-1.4 test');
        $document = CaseDocument::query()->create([
            'case_id' => $case->id,
            'kind' => 'developer_approval',
            'disk' => 'private_documents',
            'path' => 'cases/1/letter.pdf',
            'original_name' => 'letter.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 12,
        ]);

        $this->get('/dashboard/cases/documents/'.$document->id.'/download')->assertForbidden();
    }

    public function test_signed_case_document_download_is_watermarked_and_logged(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Operations);
        $case = TransferCase::factory()->create(['public_code' => 'TR-4409']);
        $pdf = (new PdfWatermarker)->minimal('Developer letter');
        Storage::disk('private_documents')->put('cases/'.$case->id.'/letter.pdf', $pdf);
        $document = CaseDocument::query()->create([
            'case_id' => $case->id,
            'kind' => 'developer_approval',
            'disk' => 'private_documents',
            'path' => 'cases/'.$case->id.'/letter.pdf',
            'original_name' => 'letter.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => strlen($pdf),
        ]);

        $url = URL::temporarySignedRoute(
            'dashboard.cases.documents.download',
            now()->addMinutes(10),
            ['caseDocument' => $document],
        );
        $body = $this->get($url)->assertOk()->streamedContent();
        $this->assertStringContainsString('Developer letter', $body);
        $this->assertStringContainsString('AQDAR staff-'.$staff->id, $body);
        $this->assertSame(1, AccessLog::query()->where('action', 'document.opened')->count());
    }

    public function test_operations_can_upload_developer_letter(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $case = TransferCase::factory()->create();

        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/documents', [
                'kind' => 'developer_approval',
                'file' => UploadedFile::fake()->create('developer.pdf', 40, 'application/pdf'),
            ])->assertRedirect();

        $this->assertSame(1, $case->documents()->count());
    }

    public function test_request_desk_matches_mockup_type_tabs_and_pipeline(): void
    {
        $this->actingAsStaff(StaffRole::Operations);

        $this->get('/dashboard/requests')
            ->assertOk()
            ->assertSee(__('aqdar.requests.all_types'), false)
            ->assertSee(__('aqdar.requests.type.apartment'), false)
            ->assertSee(__('aqdar.requests.type.villa'), false)
            ->assertSee(__('aqdar.requests.type.land'), false)
            ->assertSee(__('aqdar.requests.type.investment'), false)
            ->assertSee(__('aqdar.requests.region'), false)
            ->assertSee(__('aqdar.requests.status.converted_or_closed'), false)
            ->assertSee(__('aqdar.requests.intent.living'), false);
    }

    public function test_request_detail_shows_market_column_and_refresh(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $request = BuyerRequest::factory()->create();
        RequestMatch::factory()->create(['buyer_request_id' => $request->id, 'score' => 77]);

        $this->get('/dashboard/requests/'.$request->id)
            ->assertOk()
            ->assertSee(__('aqdar.listings.desk.market'), false)
            ->assertSee(__('aqdar.requests.refresh'), false)
            ->assertSee(__('aqdar.requests.privacy'), false);
    }

    public function test_case_desk_shows_lifecycle_filter_and_last_update(): void
    {
        $this->actingAsStaff(StaffRole::Operations);

        $this->get('/dashboard/cases')
            ->assertOk()
            ->assertSee(__('aqdar.cases.lifecycle_filter'), false)
            ->assertSee(__('aqdar.cases.lifecycle.on_hold'), false)
            ->assertSee(__('aqdar.cases.updated'), false)
            ->assertSee(__('aqdar.cases.internal_stage'), false)
            ->assertSee(__('aqdar.cases.lifecycle_column'), false)
            ->assertSee(__('aqdar.cases.client_sees'), false);
    }

    public function test_unassigned_sales_cannot_send_a_match(): void
    {
        $this->actingAsStaff(StaffRole::Sales);
        $request = BuyerRequest::factory()->create();
        $match = RequestMatch::factory()->create(['buyer_request_id' => $request->id]);

        $this->from('/dashboard/requests/'.$request->id)
            ->post('/dashboard/requests/'.$request->id.'/matches/'.$match->id.'/send')
            ->assertForbidden();
        $this->assertNull($match->fresh()->sent_at);
    }

    public function test_desk_can_create_manual_buyer_request(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $buyer = User::factory()->create(['is_buyer' => true]);

        $this->from('/dashboard/requests')
            ->post('/dashboard/requests', [
                'buyer_id' => $buyer->id,
                'intent' => 'living',
                'budget_max' => 2000000,
            ])->assertRedirect();

        $this->assertTrue(BuyerRequest::query()->where('user_id', $buyer->id)->exists());
    }
}
