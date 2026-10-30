<?php

namespace Tests\Integration;

use App\Core\Audit\Models\AuditLog;
use App\Core\Enums\LeadPriority;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingStatusHistory;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardQueueRulesTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_replacing_a_lead_owner_requires_a_reason_that_is_audited(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $first = $this->makeStaff(StaffRole::Sales);
        $second = $this->makeStaff(StaffRole::Sales);
        $lead = Lead::factory()->create(['assigned_to' => $first->id]);

        $this->from('/dashboard/leads/'.$lead->id)
            ->post('/dashboard/leads/'.$lead->id.'/assign', ['assigned_to' => $second->id])
            ->assertSessionHasErrors('reason');
        $this->assertSame($first->id, (int) $lead->fresh()->assigned_to);

        $this->from('/dashboard/leads/'.$lead->id)
            ->post('/dashboard/leads/'.$lead->id.'/assign', ['assigned_to' => $second->id, 'reason' => 'Workload balancing'])
            ->assertSessionHasNoErrors();
        $this->assertSame($second->id, (int) $lead->fresh()->assigned_to);
        $this->assertSame(
            'Workload balancing',
            AuditLog::query()->where('action', 'lead.assigned')->latest('id')->first()?->meta['reason'] ?? null,
        );
    }

    public function test_replacing_a_case_owner_requires_a_reason(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $first = $this->makeStaff(StaffRole::Sales);
        $second = $this->makeStaff(StaffRole::Sales);
        $case = TransferCase::factory()->create(['assigned_to' => $first->id]);

        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/assign', ['assigned_to' => $second->id])
            ->assertSessionHasErrors('reason');

        $this->from('/dashboard/cases/'.$case->id)
            ->post('/dashboard/cases/'.$case->id.'/assign', ['assigned_to' => $second->id, 'reason' => 'Owner on leave'])
            ->assertSessionHasNoErrors();
        $this->assertSame($second->id, (int) $case->fresh()->assigned_to);
    }

    public function test_financial_verification_requires_a_reason(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create();

        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/verify-financials', ['verified_contract_price' => 1500000])
            ->assertSessionHasErrors('reason');
    }

    public function test_staff_revoke_and_sign_out_require_a_reason_and_bump_session_version(): void
    {
        $this->actingAsStaff(StaffRole::SuperAdmin);
        $sales = $this->makeStaff(StaffRole::Sales);
        $version = (int) $sales->session_version;

        $this->post('/dashboard/staff/'.$sales->id.'/deactivate')->assertSessionHasErrors('reason');
        $this->assertTrue($sales->fresh()->is_active);

        $this->post('/dashboard/staff/'.$sales->id.'/sign-out', ['reason' => 'Lost laptop'])->assertSessionHasNoErrors();
        $this->assertSame($version + 1, (int) $sales->fresh()->session_version);
        $this->assertTrue($sales->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.sessions_revoked']);

        $this->post('/dashboard/staff/'.$sales->id.'/deactivate', ['reason' => 'Contract ended'])->assertSessionHasNoErrors();
        $this->assertFalse($sales->fresh()->is_active);
    }

    public function test_listing_detail_shows_decision_timeline_with_reason_and_actor(): void
    {
        $reviewer = $this->actingAsStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);
        ListingStatusHistory::query()->create([
            'listing_id' => $listing->id,
            'from_status' => ListingStatus::Submitted,
            'to_status' => ListingStatus::NeedsChanges,
            'actor_type' => $reviewer->getMorphClass(),
            'actor_id' => $reviewer->id,
            'reason' => 'Contract page two is missing',
        ]);

        $this->get('/dashboard/listings/'.$listing->id)
            ->assertOk()
            ->assertSee(__('aqdar.listings.desk.decision_timeline'))
            ->assertSee('Contract page two is missing')
            ->assertSee($reviewer->name);
    }

    public function test_user_edit_shows_consent_and_account_history(): void
    {
        $this->actingAsStaff(StaffRole::SuperAdmin);
        $user = User::factory()->create(['consent_version' => '3']);
        AuditLog::query()->create([
            'action' => 'user.suspended',
            'actor_type' => 'staff',
            'actor_id' => 1,
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
            'meta' => ['reason' => 'Fraud check'],
            'created_at' => now(),
        ]);

        $this->get('/dashboard/users/'.$user->id.'/edit')
            ->assertOk()
            ->assertSee(__('aqdar.users.consent_card'))
            ->assertSee(__('aqdar.users.account_history'))
            ->assertSee('Fraud check');
    }

    public function test_settings_security_tab_shows_failed_jobs_and_push_failures(): void
    {
        $this->actingAsStaff(StaffRole::SuperAdmin);

        $this->get('/dashboard/settings?tab=security')
            ->assertOk()
            ->assertSee(__('aqdar.settings.diag_failed_jobs'))
            ->assertSee(__('aqdar.settings.diag_push_failed'));
    }

    public function test_lead_queue_sorts_by_whitelisted_column_and_filters_by_date(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $low = Lead::factory()->create(['priority' => LeadPriority::Low]);
        $high = Lead::factory()->create(['priority' => LeadPriority::High]);
        $old = Lead::factory()->create(['created_at' => now()->subMonths(3)]);

        $this->get('/dashboard/leads?sort=priority&dir=asc')
            ->assertOk()
            ->assertSeeInOrder([$high->public_code, $low->public_code]);

        $this->get('/dashboard/leads?sort=password&dir=asc')->assertOk();

        $this->get('/dashboard/leads?priority=high')
            ->assertOk()
            ->assertSee($high->public_code)
            ->assertDontSee($low->public_code);

        $this->get('/dashboard/leads?from='.now()->subWeek()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertSee($high->public_code)
            ->assertDontSee($old->public_code);
    }
}
