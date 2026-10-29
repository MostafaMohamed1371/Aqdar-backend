<?php

namespace Tests\Integration;

use App\Core\Audit\Models\AccessLog;
use App\Core\Audit\Models\AuditLog;
use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\InquiryStatus;
use App\Core\Enums\LeadStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\Appointment;
use App\Modules\Crm\Models\Inquiry;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\TransferCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardCrmTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_operations_can_open_crm_desk(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $lead = Lead::factory()->create(['public_code' => 'LD-2026-00042']);

        $this->get('/dashboard/leads')
            ->assertOk()
            ->assertSee(__('aqdar.menu.adm_07'))
            ->assertSee('LD-2026-00042', false)
            ->assertSee(__('aqdar.crm.mask_note'));

        $this->get('/dashboard/leads/'.$lead->id)
            ->assertOk()
            ->assertSee('LD-2026-00042', false)
            ->assertSee(__('aqdar.crm.open_case'));
    }

    public function test_reviewer_and_finance_cannot_open_crm_desk(): void
    {
        $lead = Lead::factory()->create();

        $this->actingAsStaff(StaffRole::Reviewer);
        $this->get('/dashboard/leads')->assertForbidden();
        $this->get('/dashboard/leads/'.$lead->id)->assertForbidden();

        $this->actingAsStaff(StaffRole::Finance);
        $this->get('/dashboard/leads')->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'permission_denied']);
    }

    public function test_sales_sees_only_assigned_leads(): void
    {
        $sales = $this->actingAsStaff(StaffRole::Sales);
        $mine = Lead::factory()->create([
            'assigned_to' => $sales->id,
            'public_code' => 'LD-MINE-1',
        ]);
        $other = Lead::factory()->create(['public_code' => 'LD-OTHER-1']);

        $this->get('/dashboard/leads')
            ->assertOk()
            ->assertSee('LD-MINE-1', false)
            ->assertDontSee('LD-OTHER-1', false);

        $this->get('/dashboard/leads/'.$mine->id)->assertOk();
        $this->get('/dashboard/leads/'.$other->id)->assertForbidden();
    }

    public function test_unassigned_sales_cannot_reveal_phone(): void
    {
        $this->actingAsStaff(StaffRole::Sales);
        $lead = Lead::factory()->create();

        $this->from('/dashboard/leads')
            ->post('/dashboard/leads/'.$lead->id.'/reveal', ['party' => 'buyer'])
            ->assertForbidden();

        $this->assertSame(0, AccessLog::query()->where('action', 'lead.phone_revealed')->count());
    }

    public function test_assigned_sales_reveal_writes_access_log(): void
    {
        $sales = $this->actingAsStaff(StaffRole::Sales);
        $lead = Lead::factory()->create(['assigned_to' => $sales->id]);
        $phone = $lead->buyer->phone;

        $this->from('/dashboard/leads/'.$lead->id)
            ->post('/dashboard/leads/'.$lead->id.'/reveal', ['party' => 'buyer'])
            ->assertRedirect();

        $this->assertSame(1, AccessLog::query()->where('action', 'lead.phone_revealed')->count());
        $this->assertSame($phone, session('revealed_lead_phones')[$lead->id]['buyer']);
    }

    public function test_activity_moves_new_lead_to_contacted(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $lead = Lead::factory()->create(['status' => LeadStatus::New]);

        $this->from('/dashboard/leads/'.$lead->id)
            ->post('/dashboard/leads/'.$lead->id.'/activity', [
                'type' => 'call',
                'contacted_party' => 'buyer',
                'body' => 'مكالمة مع المشتري — مهتم بالتجمع',
            ])->assertRedirect();

        $this->assertSame(LeadStatus::Contacted, $lead->fresh()->status);
        $this->assertNotNull($lead->fresh()->last_contacted_at);
    }

    public function test_open_case_creates_transfer_case(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $lead = Lead::factory()->create(['status' => LeadStatus::Qualified]);

        $this->from('/dashboard/leads/'.$lead->id)
            ->post('/dashboard/leads/'.$lead->id.'/status', [
                'status' => 'converted',
                'reason' => 'مؤهل لفتح حالة',
            ])->assertRedirect();

        $this->assertSame(LeadStatus::Converted, $lead->fresh()->status);
        $this->assertSame('open_case', $lead->fresh()->next_action);
        $case = TransferCase::query()->where('lead_id', $lead->id)->first();
        $this->assertNotNull($case);
        $this->assertStringStartsWith('TR-', (string) $case->public_code);
        $this->get('/dashboard/leads/'.$lead->id)
            ->assertOk()
            ->assertSee($case->public_code, false);
    }

    public function test_sales_cannot_assign_leads(): void
    {
        $sales = $this->actingAsStaff(StaffRole::Sales);
        $other = $this->makeStaff(StaffRole::Sales, ['email' => 'other-sales@aqdar.test']);
        $lead = Lead::factory()->create(['assigned_to' => $sales->id]);

        $this->from('/dashboard/leads/'.$lead->id)
            ->post('/dashboard/leads/'.$lead->id.'/assign', ['assigned_to' => $other->id])
            ->assertForbidden();
    }

    public function test_operations_can_assign_lead(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $sales = $this->makeStaff(StaffRole::Sales, ['email' => 'desk-sales@aqdar.test']);
        $lead = Lead::factory()->create();

        $this->from('/dashboard/leads/'.$lead->id)
            ->post('/dashboard/leads/'.$lead->id.'/assign', ['assigned_to' => $sales->id])
            ->assertRedirect();

        $this->assertSame($sales->id, (int) $lead->fresh()->assigned_to);
        $this->assertTrue(AuditLog::query()->where('action', 'lead.assigned')->exists());
    }

    public function test_desk_shows_add_lead_stage_filter_and_sla_hours(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $lead = Lead::factory()->create([
            'public_code' => 'LD-SLA-1',
            'status' => LeadStatus::New,
            'sla_due_at' => now()->subHours(3),
        ]);

        $hours = $lead->fresh()->slaOverdueHours();
        $this->get('/dashboard/leads')
            ->assertOk()
            ->assertSee('#add-lead', false)
            ->assertSee(__('aqdar.crm.add'), false)
            ->assertSee(__('aqdar.crm.all_stages'), false)
            ->assertSee(__('aqdar.crm.sla_overdue_hours', ['hours' => $hours]), false);
    }

    public function test_assign_syncs_inquiry_and_confirming_appointment_sets_visit(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $sales = $this->makeStaff(StaffRole::Sales, ['email' => 'crm-sales@aqdar.test']);
        $lead = Lead::factory()->create(['status' => LeadStatus::New]);
        $inquiry = Inquiry::factory()->create([
            'lead_id' => $lead->id,
            'user_id' => $lead->buyer_id,
            'listing_id' => $lead->listing_id,
            'status' => InquiryStatus::New,
        ]);
        $appointment = Appointment::factory()->create([
            'lead_id' => $lead->id,
            'user_id' => $lead->buyer_id,
            'listing_id' => $lead->listing_id,
        ]);

        $this->from('/dashboard/leads/'.$lead->id)
            ->post('/dashboard/leads/'.$lead->id.'/assign', ['assigned_to' => $sales->id])
            ->assertRedirect();
        $this->assertSame(InquiryStatus::InReview, $inquiry->fresh()->status);

        $this->get('/dashboard/leads/'.$lead->id)
            ->assertOk()
            ->assertSee(__('aqdar.crm.confirm_appointment'))
            ->assertSee(__('aqdar.crm.cancel_appointment'));

        $this->from('/dashboard/leads/'.$lead->id)
            ->post('/dashboard/leads/'.$lead->id.'/appointments/'.$appointment->id, ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertSame(LeadStatus::VisitScheduled, $lead->fresh()->status);
        $this->assertSame(InquiryStatus::Replied, $inquiry->fresh()->status);
    }
}
