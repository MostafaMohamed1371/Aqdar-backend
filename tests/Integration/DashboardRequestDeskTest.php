<?php

namespace Tests\Integration;

use App\Core\Enums\BuyerRequestStatus;
use App\Core\Enums\LeadStatus;
use App\Core\Enums\SegmentPreference;
use App\Core\Enums\StaffRole;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Dashboard\Services\ReportService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardRequestDeskTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_repeat_request_with_same_criteria_is_flagged_as_duplicate(): void
    {
        $buyer = User::factory()->create(['is_buyer' => true]);
        $governorate = Governorate::factory()->create();
        Sanctum::actingAs($buyer);
        $payload = ['intent' => 'living', 'contact_method' => 'phone', 'consent' => true, 'governorate_id' => $governorate->id];

        $this->postJson('/api/v1/buyer-requests', $payload)->assertCreated();
        $this->postJson('/api/v1/buyer-requests', [...$payload, 'intent' => 'investment'])->assertCreated();
        $this->postJson('/api/v1/buyer-requests', $payload)->assertCreated();

        [$first, $otherIntent, $repeat] = BuyerRequest::query()->orderBy('id')->get()->all();
        $this->assertFalse($first->is_duplicate);
        $this->assertFalse($otherIntent->is_duplicate);
        $this->assertTrue($repeat->is_duplicate);
        $this->assertSame($first->id, $repeat->duplicate_of_id);

        $this->actingAsStaff(StaffRole::Operations);
        $this->get('/dashboard/requests?duplicates=1')
            ->assertOk()
            ->assertSee($repeat->public_code)
            ->assertDontSee($first->public_code);
        $this->get('/dashboard/requests/'.$repeat->id)
            ->assertOk()
            ->assertSee(__('aqdar.requests.duplicate_of', ['code' => $first->public_code]), false)
            ->assertSee(__('aqdar.requests.history'));
    }

    public function test_desk_filters_by_owner_region_segment_budget_and_date(): void
    {
        $sales = $this->makeStaff(StaffRole::Sales);
        $cairo = Governorate::factory()->create();
        $match = BuyerRequest::factory()->create([
            'assigned_to' => $sales->id,
            'governorate_id' => $cairo->id,
            'segment_preference' => SegmentPreference::Distressed,
            'budget_min' => 2000000,
            'budget_max' => 3000000,
        ]);
        $wrongBudget = BuyerRequest::factory()->create([
            'assigned_to' => $sales->id,
            'governorate_id' => $cairo->id,
            'segment_preference' => SegmentPreference::Distressed,
            'budget_min' => 9000000,
            'budget_max' => 12000000,
        ]);
        $unassigned = BuyerRequest::factory()->create(['governorate_id' => $cairo->id]);
        $old = BuyerRequest::factory()->create(['assigned_to' => $sales->id, 'created_at' => now()->subMonths(2)]);
        $this->actingAsStaff(StaffRole::Operations);

        $this->get('/dashboard/requests?'.http_build_query([
            'assigned_to' => $sales->id,
            'governorate_id' => $cairo->id,
            'segment' => 'distressed',
            'budget_max' => 4000000,
        ]))->assertOk()
            ->assertSee($match->public_code)
            ->assertDontSee($wrongBudget->public_code)
            ->assertDontSee($unassigned->public_code);

        $this->get('/dashboard/requests?assigned_to=unassigned')
            ->assertSee($unassigned->public_code)
            ->assertDontSee($match->public_code);

        $this->get('/dashboard/requests?from='.now()->subWeek()->toDateString())
            ->assertSee($match->public_code)
            ->assertDontSee($old->public_code);

        $this->get('/dashboard/requests?sort=budget&dir=asc')
            ->assertOk()
            ->assertSeeInOrder([$match->public_code, $wrongBudget->public_code]);
    }

    public function test_overdue_filter_uses_linked_lead_follow_up(): void
    {
        $overdueLead = Lead::factory()->create(['status' => LeadStatus::New, 'sla_due_at' => now()->subHour()]);
        $late = BuyerRequest::factory()->create(['lead_id' => $overdueLead->id]);
        $onTime = BuyerRequest::factory()->create(['lead_id' => Lead::factory()->create(['sla_due_at' => now()->addDay()])->id]);
        $this->actingAsStaff(StaffRole::Operations);

        $this->get('/dashboard/requests?overdue=1')
            ->assertOk()
            ->assertSee($late->public_code)
            ->assertDontSee($onTime->public_code);
    }

    public function test_reports_count_request_to_qualified_and_transaction_conversion(): void
    {
        BuyerRequest::factory()->create();
        BuyerRequest::factory()->create([
            'lead_id' => Lead::factory()->create(['status' => LeadStatus::Qualified])->id,
        ]);
        $closed = BuyerRequest::factory()->create(['status' => BuyerRequestStatus::Converted]);
        TransferCase::factory()->create(['buyer_request_id' => $closed->id]);
        BuyerRequest::factory()->create();

        $rates = app(ReportService::class)->rates([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $this->assertSame(50.0, $rates['request_qualified_rate']);
        $this->assertSame(25.0, $rates['request_transaction_rate']);
    }
}
