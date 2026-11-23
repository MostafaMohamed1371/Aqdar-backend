<?php

namespace Tests\Integration;

use App\Core\Enums\CommissionStatus;
use App\Core\Enums\LeadStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\Lead;
use App\Modules\Dashboard\Services\ReportAnalyticsService;
use App\Modules\Finance\Models\Commission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class ReportAnalyticsTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    private function commission(CommissionStatus $status, float $amount, string $createdAt, float $tax = 0): Commission
    {
        return Commission::factory()->create([
            'status' => $status,
            'amount' => $amount,
            'tax_amount' => $tax,
            'created_at' => Carbon::parse($createdAt),
        ]);
    }

    public function test_ledger_groups_by_status_and_month_and_excludes_waived_and_reversed_from_net(): void
    {
        $this->commission(CommissionStatus::Paid, 1000, '2026-03-05', 140);
        $this->commission(CommissionStatus::Due, 500, '2026-03-20');
        $this->commission(CommissionStatus::Earned, 250, '2026-04-02');
        $this->commission(CommissionStatus::Waived, 9000, '2026-04-03');
        $this->commission(CommissionStatus::Reversed, 7000, '2026-04-04');
        $this->commission(CommissionStatus::Due, 100, '2026-01-01'); // outside the range, still receivable

        $ledger = app(ReportAnalyticsService::class)->ledger(['from' => '2026-03-01', 'to' => '2026-04-30']);

        $byStatus = collect($ledger['by_status'])->keyBy('status');
        $this->assertSame(1000.0, $byStatus['paid']['amount']);
        $this->assertSame(140.0, $byStatus['paid']['tax_amount']);
        $this->assertSame(1, $byStatus['waived']['count']);
        $this->assertSame(1750.0, $ledger['net_revenue']);
        $this->assertSame(850.0, $ledger['receivable']);

        $months = collect($ledger['months'])->keyBy('key');
        $this->assertSame(1500.0, $months['2026-03']['net']);
        $this->assertSame(250.0, $months['2026-04']['net']);
        $this->assertSame(16250.0, $months['2026-04']['amount']);
    }

    public function test_forecast_is_a_flat_run_rate_of_the_last_full_months(): void
    {
        $this->commission(CommissionStatus::Paid, 30000, '2026-05-10');
        $this->commission(CommissionStatus::Due, 60000, '2026-04-10');
        $this->commission(CommissionStatus::Earned, 90000, '2026-03-10');
        $this->commission(CommissionStatus::Waived, 999999, '2026-05-11');
        $this->commission(CommissionStatus::Paid, 5000000, '2026-06-02'); // current month is not part of the basis

        $forecast = app(ReportAnalyticsService::class)->forecast(Carbon::parse('2026-06-15'));

        $this->assertSame(['2026-03', '2026-04', '2026-05'], array_column($forecast['basis'], 'key'));
        $this->assertSame(60000.0, $forecast['run_rate']);
        $this->assertSame(['2026-06', '2026-07', '2026-08'], array_column($forecast['projection'], 'key'));
        $this->assertSame([60000.0, 60000.0, 60000.0], array_column($forecast['projection'], 'amount'));
        $this->assertTrue($forecast['enough_history']);
    }

    public function test_forecast_without_history_is_zero_and_says_so(): void
    {
        $forecast = app(ReportAnalyticsService::class)->forecast(Carbon::parse('2026-06-15'));

        $this->assertSame(0.0, $forecast['run_rate']);
        $this->assertFalse($forecast['enough_history']);
    }

    public function test_funnel_counts_leads_per_stage(): void
    {
        Lead::factory()->count(2)->create(['status' => LeadStatus::New]);
        Lead::factory()->create(['status' => LeadStatus::Converted]);

        $funnel = app(ReportAnalyticsService::class)->funnel([]);
        $leads = collect($funnel['leads'])->keyBy('key');

        $this->assertSame(2, $leads['new']['count']);
        $this->assertSame(1, $leads['converted']['count']);
        $this->assertSame(66.7, $leads['new']['percent']);
        $this->assertSame(0, $leads['lost']['count']);
    }

    public function test_reports_page_and_ledger_export_for_finance(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        $this->commission(CommissionStatus::Paid, 1234.5, now()->toDateString());

        $this->get(route('dashboard.reports.index'))
            ->assertOk()
            ->assertSee('data-section="ledger"', false)
            ->assertSee('data-section="forecast"', false)
            ->assertSee('data-section="funnel"', false)
            ->assertSee('1,234.50');

        $csv = $this->get(route('dashboard.reports.export', ['type' => 'ledger']))->assertOk()->streamedContent();
        $this->assertStringContainsString('month,status,count,amount,tax_amount', $csv);
        $this->assertStringContainsString(now()->format('Y-m').',paid,1,1234.50,0.00', $csv);
    }

    public function test_staff_without_report_access_cannot_open_the_ledger_export(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);

        $this->get(route('dashboard.reports.export', ['type' => 'ledger']))->assertForbidden();
    }
}
