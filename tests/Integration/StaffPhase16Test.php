<?php

namespace Tests\Integration;

use App\Core\Enums\CaseStage;
use App\Core\Enums\CaseStatus;
use App\Core\Enums\CommissionStatus;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\Permission;
use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Finance\Models\Commission;
use App\Modules\Listing\Models\Listing;
use App\Modules\Staff\Services\StaffService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class StaffPhase16Test extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_reviewer_cannot_edit_commission(): void
    {
        $commission = Commission::factory()->create(['status' => CommissionStatus::Due]);

        $this->actingAsStaff(StaffRole::Reviewer);
        $this->from('/dashboard/commissions')
            ->patch('/dashboard/commissions/'.$commission->id, [
                'status' => 'paid',
                'reason' => 'محاولة غير مصرح بها',
            ])->assertForbidden();
        $this->assertSame(CommissionStatus::Due, $commission->fresh()->status);
    }

    public function test_finance_cannot_approve_listings(): void
    {
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);

        $this->actingAsStaff(StaffRole::Finance);
        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/approve', ['reason' => 'اعتماد'])
            ->assertForbidden();
        $this->assertSame(ListingStatus::InReview, $listing->fresh()->status);
    }

    public function test_sales_sees_own_commission_row_only(): void
    {
        $sales = $this->actingAsStaff(StaffRole::Sales);
        $mine = TransferCase::factory()->create([
            'assigned_to' => $sales->id,
            'status' => CaseStatus::Completed,
            'public_code' => 'TR-MINE-C1',
        ]);
        $other = TransferCase::factory()->create([
            'status' => CaseStatus::Completed,
            'public_code' => 'TR-OTHER-C1',
        ]);
        Commission::factory()->create(['case_id' => $mine->id, 'listing_id' => $mine->listing_id]);
        Commission::factory()->create(['case_id' => $other->id, 'listing_id' => $other->listing_id]);

        $this->get('/dashboard/commissions')
            ->assertOk()
            ->assertSee('TR-MINE-C1', false)
            ->assertDontSee('TR-OTHER-C1', false)
            ->assertDontSee('#add-commission', false)
            ->assertDontSee(__('aqdar.commissions.csv'), false)
            ->assertSee(__('aqdar.commissions.own_only_note'));

        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions', [
                'case_id' => $mine->id,
                'payer_party' => 'buyer',
                'basis' => 'percent',
                'rate' => 2.5,
                'sale_value' => 1000000,
                'status' => 'earned',
            ])->assertForbidden();
        $this->from('/dashboard/commissions')
            ->patch('/dashboard/commissions/'.Commission::query()->where('case_id', $mine->id)->value('id'), [
                'status' => 'paid',
                'reason' => 'محاولة تعديل من المبيعات',
            ])->assertForbidden();
        $this->get('/dashboard/commissions/export')->assertForbidden();
    }

    public function test_finance_opens_cases_and_advances_finance_stages_only(): void
    {
        $early = TransferCase::factory()->create([
            'internal_stage' => CaseStage::QualifyBuyer,
            'public_code' => 'TR-EARLY-16',
        ]);
        $ready = TransferCase::factory()->create([
            'internal_stage' => CaseStage::DeveloperApproval,
            'public_code' => 'TR-READY-16',
        ]);

        $this->actingAsStaff(StaffRole::Finance);

        $this->get('/dashboard/leads')->assertForbidden();
        $this->get('/dashboard/requests')->assertForbidden();
        $this->get('/dashboard/cases')
            ->assertOk()
            ->assertSee('TR-EARLY-16', false)
            ->assertSee('TR-READY-16', false)
            ->assertDontSee('#add-case', false)
            ->assertSee(__('aqdar.cases.finance_only_note'));

        $this->get('/dashboard/cases/'.$early->id)
            ->assertOk()
            ->assertSee(__('aqdar.cases.finance_not_ready'));
        $this->get('/dashboard/cases/'.$ready->id)
            ->assertOk()
            ->assertSee('TR-READY-16', false)
            ->assertDontSee(__('aqdar.cases.upload_letter'), false);

        $this->from('/dashboard/cases/'.$early->id)
            ->post('/dashboard/cases/'.$early->id.'/stage', [
                'stage' => 'qualify_buyer',
                'reason' => 'محاولة مالية على خط المبيعات',
            ])->assertForbidden();
        $this->from('/dashboard/cases/'.$early->id)
            ->post('/dashboard/cases/'.$early->id.'/stage', [
                'stage' => 'schedule_transfer',
                'reason' => 'جدولة مبكرة من المالية',
            ])->assertForbidden();
        $this->assertSame(CaseStage::QualifyBuyer, $early->fresh()->internal_stage);

        $this->from('/dashboard/cases/'.$ready->id)
            ->post('/dashboard/cases/'.$ready->id.'/stage', [
                'stage' => 'schedule_transfer',
                'reason' => 'جدولة النقل',
            ])->assertRedirect();
        $this->assertSame(CaseStage::ScheduleTransfer, $ready->fresh()->internal_stage);

        $this->from('/dashboard/cases/'.$ready->id)
            ->post('/dashboard/cases/'.$ready->id.'/lifecycle', [
                'status' => 'on_hold',
                'reason' => 'محاولة إيقاف من المالية',
            ])->assertForbidden();
        $this->assertSame(CaseStatus::Open, $ready->fresh()->status);

        $this->from('/dashboard/cases/'.$ready->id)
            ->post('/dashboard/cases/'.$ready->id.'/stage', [
                'stage' => 'completed',
                'reason' => 'إتمام النقل',
            ])->assertRedirect();
        $this->assertSame(CaseStage::Completed, $ready->fresh()->internal_stage);
        $this->assertSame(CaseStatus::Completed, $ready->fresh()->status);
    }

    public function test_operations_approve_commission_and_cannot_edit_amount(): void
    {
        $case = TransferCase::factory()->create(['status' => CaseStatus::Completed]);
        $commission = Commission::factory()->create([
            'case_id' => $case->id,
            'listing_id' => $case->listing_id,
            'amount' => 80000,
            'status' => CommissionStatus::Due,
        ]);

        $ops = $this->actingAsStaff(StaffRole::Operations);

        $this->get('/dashboard/commissions')
            ->assertOk()
            ->assertSee($case->public_code, false)
            ->assertSee(__('aqdar.commissions.pending'))
            ->assertSee(__('aqdar.commissions.approve'))
            ->assertDontSee('#add-commission', false);

        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions', [
                'case_id' => $case->id,
                'payer_party' => 'buyer',
                'basis' => 'percent',
                'rate' => 2.5,
                'sale_value' => 1000000,
                'status' => 'earned',
            ])->assertForbidden();

        $this->from('/dashboard/commissions')
            ->patch('/dashboard/commissions/'.$commission->id, [
                'status' => 'paid',
                'reason' => 'محاولة تعديل من العمليات',
            ])->assertForbidden();
        $this->assertEquals(80000, (float) $commission->fresh()->amount);

        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions/'.$commission->id.'/approve', [
                'reason' => 'اعتماد التعديل',
            ])->assertRedirect();

        $fresh = $commission->fresh();
        $this->assertNotNull($fresh->approved_at);
        $this->assertSame($ops->id, (int) $fresh->approved_by);
        $this->assertDatabaseHas('commission_history', [
            'commission_id' => $commission->id,
            'reason' => 'اعتماد التعديل',
            'actor_id' => $ops->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'commission.approved']);
    }

    public function test_finance_create_stays_pending_until_operations_approve(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        $case = TransferCase::factory()->create(['status' => CaseStatus::Completed]);

        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions', [
                'case_id' => $case->id,
                'payer_party' => 'buyer',
                'basis' => 'percent',
                'rate' => 2.5,
                'sale_value' => 3200000,
                'status' => 'earned',
                'reason' => 'صفقة مكتملة',
            ])->assertRedirect();

        $row = Commission::query()->where('case_id', $case->id)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->approved_at);

        $this->actingAsStaff(StaffRole::Operations);
        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions/'.$row->id.'/approve', [
                'reason' => 'اعتماد المالية',
            ])->assertRedirect();
        $this->assertNotNull($row->fresh()->approved_at);
    }

    public function test_sales_cannot_approve_commission(): void
    {
        $commission = Commission::factory()->create();

        $this->actingAsStaff(StaffRole::Sales);
        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions/'.$commission->id.'/approve', [
                'reason' => 'محاولة اعتماد',
            ])->assertForbidden();
        $this->assertNull($commission->fresh()->approved_at);
    }

    public function test_finance_cannot_create_assign_or_upload_on_cases(): void
    {
        $finance = $this->actingAsStaff(StaffRole::Finance);
        $case = TransferCase::factory()->create([
            'internal_stage' => CaseStage::DeveloperApproval,
        ]);
        $buyer = User::factory()->create(['is_buyer' => true]);
        $other = $this->makeStaff(StaffRole::Sales, ['email' => 'other-fin16@aqdar.test']);

        $this->from('/dashboard/cases')
            ->post('/dashboard/cases', [
                'buyer_id' => $buyer->id,
                'reason' => 'محاولة فتح حالة من المالية',
            ])->assertForbidden();
        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/assign', [
                'assigned_to' => $other->id,
            ])->assertForbidden();
        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/documents', [
                'kind' => 'developer_approval',
                'file' => UploadedFile::fake()->create('developer.pdf', 40, 'application/pdf'),
            ])->assertForbidden();
        $this->assertSame(0, TransferCase::query()->where('buyer_id', $buyer->id)->count());
        $this->assertNotSame($other->id, (int) $case->fresh()->assigned_to);
        $this->assertSame(0, $case->documents()->count());
        $this->assertNotSame($finance->id, $other->id);
    }

    public function test_finance_update_repends_approved_commission(): void
    {
        $finance = $this->actingAsStaff(StaffRole::Finance);
        $case = TransferCase::factory()->create(['status' => CaseStatus::Completed]);

        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions', [
                'case_id' => $case->id,
                'payer_party' => 'buyer',
                'basis' => 'percent',
                'rate' => 2.5,
                'sale_value' => 3200000,
                'status' => 'earned',
                'reason' => 'صفقة مكتملة',
            ])->assertRedirect();

        $row = Commission::query()->where('case_id', $case->id)->first();
        $this->assertNotNull($row);

        $this->actingAsStaff(StaffRole::Operations);
        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions/'.$row->id.'/approve', [
                'reason' => 'اعتماد أولي',
            ])->assertRedirect();
        $this->assertNotNull($row->fresh()->approved_at);

        $this->actingAs($finance);
        $this->from('/dashboard/commissions')
            ->patch('/dashboard/commissions/'.$row->id, [
                'status' => 'paid',
                'reason' => 'تعديل بعد الاعتماد',
            ])->assertRedirect();
        $this->assertNull($row->fresh()->approved_at);
        $this->assertSame(CommissionStatus::Paid, $row->fresh()->status);
    }

    public function test_super_admin_commission_is_auto_approved(): void
    {
        $admin = $this->actingAsStaff(StaffRole::SuperAdmin);
        $case = TransferCase::factory()->create(['status' => CaseStatus::Completed]);

        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions', [
                'case_id' => $case->id,
                'payer_party' => 'buyer',
                'basis' => 'percent',
                'rate' => 2.5,
                'sale_value' => 3200000,
                'status' => 'earned',
                'reason' => 'اعتماد تلقائي للمدير',
            ])->assertRedirect();

        $row = Commission::query()->where('case_id', $case->id)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->approved_at);
        $this->assertSame($admin->id, (int) $row->approved_by);
    }

    public function test_adm06_matrix_matches_brd_phase16(): void
    {
        $this->actingAsStaff(StaffRole::SuperAdmin);
        $matrix = app(StaffService::class)->permissionMatrix();

        $this->assertTrue($matrix[Permission::AdvanceFinanceStages->value][StaffRole::Finance->value]);
        $this->assertFalse($matrix[Permission::AdvanceCase->value][StaffRole::Finance->value]);
        $this->assertTrue($matrix[Permission::ApproveCommissions->value][StaffRole::Operations->value]);
        $this->assertFalse($matrix[Permission::EditCommissions->value][StaffRole::Operations->value]);
        $this->assertTrue($matrix[Permission::ViewCommissions->value][StaffRole::Sales->value]);
        $this->assertFalse($matrix[Permission::EditCommissions->value][StaffRole::Sales->value]);
        $this->assertFalse($matrix[Permission::ApproveCommissions->value][StaffRole::Sales->value]);
        $this->assertFalse($matrix[Permission::ViewCommissions->value][StaffRole::Reviewer->value]);
        $this->assertFalse($matrix[Permission::EditCommissions->value][StaffRole::Reviewer->value]);
        $this->assertTrue($matrix[Permission::AdvanceFinanceStages->value][StaffRole::SuperAdmin->value]);

        $this->get('/dashboard/staff')
            ->assertOk()
            ->assertSee(Permission::AdvanceFinanceStages->labelAr(), false)
            ->assertSee(Permission::ApproveCommissions->labelAr(), false)
            ->assertSee(Permission::ViewCommissions->labelAr(), false);
    }

    public function test_finance_sidebar_links_cases_not_leads(): void
    {
        $this->actingAsStaff(StaffRole::Finance);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('dashboard.cases.index').'"', false)
            ->assertSee('href="'.route('dashboard.commissions.index').'"', false)
            ->assertDontSee('href="'.route('dashboard.leads.index').'"', false)
            ->assertDontSee('href="'.route('dashboard.requests.index').'"', false)
            ->assertDontSee('href="'.route('dashboard.listings.index').'"', false);
    }

    public function test_sales_sidebar_links_own_commissions(): void
    {
        $this->actingAsStaff(StaffRole::Sales);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('dashboard.commissions.index').'"', false)
            ->assertSee('href="'.route('dashboard.cases.index').'"', false);
    }
}
