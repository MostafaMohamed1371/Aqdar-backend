<?php

namespace Tests\Integration;

use App\Core\Audit\Models\AuditLog;
use App\Core\Enums\CaseStatus;
use App\Core\Enums\CommissionStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Finance\Models\Commission;
use App\Modules\Listing\Models\Listing;
use App\Modules\Setting\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardCommissionTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_finance_can_open_commission_desk(): void
    {
        $this->actingAsStaff(StaffRole::Finance);

        $this->get('/dashboard/commissions')
            ->assertOk()
            ->assertSee(__('aqdar.menu.adm_10'))
            ->assertSee(__('aqdar.commissions.add'), false)
            ->assertSee(__('aqdar.commissions.csv'), false)
            ->assertSee('#add-commission', false)
            ->assertSee(__('aqdar.commissions.kpi.total'))
            ->assertSee(__('aqdar.commissions.kpi.due'))
            ->assertSee(__('aqdar.commissions.kpi.deals'))
            ->assertSee(__('aqdar.commissions.kpi.average'))
            ->assertSee(__('aqdar.commissions.payer'))
            ->assertSee(__('aqdar.commissions.sale_value'))
            ->assertSee(__('aqdar.commissions.no_closed_cases'))
            ->assertSee(__('aqdar.commissions.charts.monthly'))
            ->assertSee(__('aqdar.commissions.charts.by_type'))
            ->assertSee(__('aqdar.commissions.charts.revenue'))
            ->assertSee(__('aqdar.commissions.charts.commissions'))
            ->assertSee(__('aqdar.requests.type.apartment'))
            ->assertSee(__('aqdar.requests.type.villa'))
            ->assertSee(__('aqdar.requests.type.land'))
            ->assertSee(__('aqdar.requests.type.investment'))
            ->assertSee(__('aqdar.commissions.all_months'))
            ->assertSee(__('aqdar.commissions.recent'))
            ->assertSee('name="month"', false)
            ->assertSee('data-chart="monthly"', false)
            ->assertSee('data-chart="by-type"', false)
            ->assertSee('<svg', false)
            ->assertSee('<rect', false);
    }

    public function test_sales_cannot_open_commission_desk(): void
    {
        $this->actingAsStaff(StaffRole::Sales);
        $this->get('/dashboard/commissions')->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'permission_denied']);
    }

    public function test_reviewer_cannot_open_or_patch_commissions(): void
    {
        $case = TransferCase::factory()->create(['status' => CaseStatus::Completed]);
        $commission = Commission::factory()->create(['case_id' => $case->id, 'listing_id' => $case->listing_id]);

        $this->actingAsStaff(StaffRole::Reviewer);
        $this->get('/dashboard/commissions')->assertForbidden();
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
                'reason' => 'محاولة غير مصرح بها',
            ])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'permission_denied']);
    }

    public function test_operations_cannot_open_commission_desk(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $this->get('/dashboard/commissions')->assertForbidden();
    }

    public function test_finance_records_commission_on_completed_case_and_audit_exists(): void
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
        $this->assertEquals(80000, (float) $row->amount);
        $this->assertSame(CommissionStatus::Earned, $row->status);
        $this->assertTrue(AuditLog::query()->where('action', 'commission.created')->exists());
        $this->assertDatabaseHas('commission_history', [
            'commission_id' => $row->id,
            'reason' => 'صفقة مكتملة',
        ]);
    }

    public function test_finance_cannot_create_commission_on_open_case(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        $case = TransferCase::factory()->create(['status' => CaseStatus::Open]);

        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions', [
                'case_id' => $case->id,
                'payer_party' => 'seller',
                'basis' => 'percent',
                'rate' => 2,
                'sale_value' => 1000000,
                'status' => 'due',
            ])->assertRedirect()
            ->assertSessionHasErrors('case_id');
        $this->assertSame(0, Commission::query()->count());
    }

    public function test_finance_patch_updates_status_with_reason_and_audit(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Finance);
        $commission = Commission::factory()->create(['status' => CommissionStatus::Due]);

        $this->from('/dashboard/commissions')
            ->patch('/dashboard/commissions/'.$commission->id, [
                'status' => 'paid',
                'invoice_reference' => 'INV-0912',
                'reason' => 'تم التحصيل',
            ])->assertRedirect();

        $this->assertSame(CommissionStatus::Paid, $commission->fresh()->status);
        $this->assertSame('INV-0912', $commission->fresh()->invoice_reference);
        $this->assertDatabaseHas('commission_history', [
            'commission_id' => $commission->id,
            'new_status' => 'paid',
            'reason' => 'تم التحصيل',
            'actor_id' => $staff->id,
        ]);
        $this->assertTrue(AuditLog::query()->where('action', 'commission.updated')->exists());
    }

    public function test_csv_export_has_no_phone_columns(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        Commission::factory()->create();

        $csv = $this->get('/dashboard/commissions/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('case', $csv);
        $this->assertStringContainsString('amount', $csv);
        $this->assertStringNotContainsString('phone', $csv);
        $this->assertStringNotContainsString('email', $csv);
    }

    public function test_default_rate_comes_from_settings(): void
    {
        Setting::query()->create([
            'key' => 'commission_default_rate',
            'value' => '3.1',
        ]);
        $this->actingAsStaff(StaffRole::Finance);

        $this->get('/dashboard/commissions')
            ->assertOk()
            ->assertSee('3.1', false);
    }

    public function test_finance_desk_shows_listing_summary_case_code_and_last_change(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Finance);
        $openForSelect = TransferCase::factory()->create([
            'status' => CaseStatus::Completed,
            'public_code' => 'TR-2026-00090',
        ]);
        $case = TransferCase::factory()->create([
            'status' => CaseStatus::Completed,
            'public_code' => 'TR-2026-00091',
        ]);
        $listing = $case->listing()->with(['unitType', 'area'])->first();

        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions', [
                'case_id' => $case->id,
                'payer_party' => 'buyer',
                'basis' => 'percent',
                'rate' => 2.5,
                'sale_value' => 3200000,
                'status' => 'earned',
                'invoice_reference' => 'INV-0912',
                'reason' => 'صفقة مكتملة',
            ])->assertRedirect();

        $this->from('/dashboard/commissions')
            ->patch('/dashboard/commissions/'.Commission::query()->where('case_id', $case->id)->value('id'), [
                'status' => 'paid',
                'invoice_reference' => 'INV-0912',
                'reason' => 'تم التحصيل',
            ])->assertRedirect();

        $page = $this->get('/dashboard/commissions')->assertOk();
        $page->assertSee('TR-2026-00091', false)
            ->assertSee('INV-0912', false)
            ->assertSee($listing?->deskSummary(), false)
            ->assertSee(__('aqdar.commissions.status.paid'))
            ->assertSee(__('aqdar.commissions.status.earned').' ← '.__('aqdar.commissions.status.paid'), false)
            ->assertSee('تم التحصيل', false)
            ->assertSee($staff->name, false)
            ->assertSee('TR-2026-00090', false)
            ->assertSee(__('aqdar.commissions.choose_case'))
            ->assertSee(__('aqdar.commissions.closed_case'))
            ->assertSee(__('aqdar.commissions.rate'));
        $page->assertDontSee('name="case_id">'.$case->id, false);
        $this->assertNotNull($openForSelect->id);
    }

    public function test_finance_cannot_create_duplicate_commission(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        $case = TransferCase::factory()->create(['status' => CaseStatus::Completed]);
        Commission::factory()->create(['case_id' => $case->id, 'listing_id' => $case->listing_id]);

        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions', [
                'case_id' => $case->id,
                'payer_party' => 'buyer',
                'basis' => 'percent',
                'rate' => 2.5,
                'sale_value' => 1000000,
                'status' => 'earned',
            ])->assertRedirect()
            ->assertSessionHasErrors('case_id');
        $this->assertSame(1, Commission::query()->count());
    }

    public function test_finance_update_requires_reason(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        $commission = Commission::factory()->create(['status' => CommissionStatus::Due]);

        $this->from('/dashboard/commissions')
            ->patch('/dashboard/commissions/'.$commission->id, [
                'status' => 'paid',
            ])->assertRedirect()
            ->assertSessionHasErrors('reason');
        $this->assertSame(CommissionStatus::Due, $commission->fresh()->status);
    }

    public function test_finance_records_fixed_amount_commission(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        $case = TransferCase::factory()->create(['status' => CaseStatus::Completed]);

        $this->from('/dashboard/commissions')
            ->post('/dashboard/commissions', [
                'case_id' => $case->id,
                'payer_party' => 'both',
                'basis' => 'fixed',
                'amount' => 68750,
                'sale_value' => 2750000,
                'status' => 'earned',
            ])->assertRedirect();

        $row = Commission::query()->where('case_id', $case->id)->first();
        $this->assertNotNull($row);
        $this->assertEquals(68750, (float) $row->amount);
        $this->assertNull($row->rate);
    }

    public function test_month_filter_limits_table_kpis_and_csv(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        $january = TransferCase::factory()->create([
            'status' => CaseStatus::Completed,
            'public_code' => 'TR-2026-00101',
        ]);
        $february = TransferCase::factory()->create([
            'status' => CaseStatus::Completed,
            'public_code' => 'TR-2026-00102',
        ]);
        Commission::factory()->create([
            'case_id' => $january->id,
            'listing_id' => $january->listing_id,
            'sale_value' => 1000000,
            'amount' => 25000,
            'status' => CommissionStatus::Paid,
        ])->forceFill(['created_at' => '2026-01-12 10:00:00'])->save();
        Commission::factory()->create([
            'case_id' => $february->id,
            'listing_id' => $february->listing_id,
            'sale_value' => 2000000,
            'amount' => 50000,
            'status' => CommissionStatus::Paid,
        ])->forceFill(['created_at' => '2026-02-10 10:00:00'])->save();

        $page = $this->get('/dashboard/commissions?month=2026-01')->assertOk();
        $page->assertSee('TR-2026-00101', false)
            ->assertDontSee('TR-2026-00102', false)
            ->assertSee('25,000', false)
            ->assertSee(__('aqdar.commissions.month_name.1').' 2026', false)
            ->assertSee(__('aqdar.commissions.month_name.1'), false)
            ->assertSee(__('aqdar.commissions.month_name.2'), false);

        $csv = $this->get('/dashboard/commissions/export?month=2026-01')->assertOk()->streamedContent();
        $this->assertStringContainsString('TR-2026-00101', $csv);
        $this->assertStringNotContainsString('TR-2026-00102', $csv);
    }

    public function test_donut_uses_unit_type_buckets_and_excludes_waived(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        $apartment = UnitType::factory()->create(['slug' => 'apartment']);
        $villa = UnitType::factory()->create(['slug' => 'villa']);
        $aptListing = Listing::factory()->published()->create(['unit_type_id' => $apartment->id]);
        $villaListing = Listing::factory()->published()->create(['unit_type_id' => $villa->id]);
        $aptCase = TransferCase::factory()->create([
            'status' => CaseStatus::Completed,
            'listing_id' => $aptListing->id,
            'seller_id' => $aptListing->user_id,
        ]);
        $villaCase = TransferCase::factory()->create([
            'status' => CaseStatus::Completed,
            'listing_id' => $villaListing->id,
            'seller_id' => $villaListing->user_id,
        ]);
        Commission::factory()->create([
            'case_id' => $aptCase->id,
            'listing_id' => $aptListing->id,
            'sale_value' => 750000,
            'amount' => 18750,
            'status' => CommissionStatus::Paid,
        ]);
        Commission::factory()->create([
            'case_id' => $villaCase->id,
            'listing_id' => $villaListing->id,
            'sale_value' => 250000,
            'amount' => 6250,
            'status' => CommissionStatus::Waived,
        ]);

        $this->get('/dashboard/commissions')
            ->assertOk()
            ->assertSee('100%', false)
            ->assertSee('0%', false)
            ->assertSee('750K', false)
            ->assertSee('data-chart="by-type"', false);
    }
}
