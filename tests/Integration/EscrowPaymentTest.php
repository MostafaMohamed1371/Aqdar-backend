<?php

namespace Tests\Integration;

use App\Core\Enums\CaseStatus;
use App\Core\Enums\EscrowStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Finance\Models\EscrowPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class EscrowPaymentTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_finance_records_an_offline_payment_held_for_the_case(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Finance);
        $case = TransferCase::factory()->create();

        $this->post(route('dashboard.cases.escrow.store', $case), [
            'amount' => '250000.50',
            'method' => 'bank_transfer',
            'reference' => 'TRF-991',
            'note' => 'first installment',
        ])->assertSessionHas('status');

        $payment = EscrowPayment::query()->where('case_id', $case->id)->firstOrFail();
        $this->assertSame(EscrowStatus::Held, $payment->status);
        $this->assertSame('250000.50', (string) $payment->amount);
        $this->assertSame($staff->id, $payment->recorded_by_staff_id);
    }

    public function test_finance_cannot_release_or_refund_a_payment(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        $payment = EscrowPayment::factory()->create();

        $this->post(route('dashboard.escrow.settle', $payment), ['status' => 'released', 'note' => 'approved'])
            ->assertForbidden();

        $this->assertSame(EscrowStatus::Held, $payment->fresh()->status);
    }

    public function test_operations_can_release_a_held_payment_with_a_reason(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Operations);
        $payment = EscrowPayment::factory()->create();

        $this->post(route('dashboard.escrow.settle', $payment), ['status' => 'released', 'note' => 'title deed delivered'])
            ->assertSessionHas('status');

        $payment = $payment->fresh();
        $this->assertSame(EscrowStatus::Released, $payment->status);
        $this->assertSame($staff->id, $payment->settled_by_staff_id);
        $this->assertNotNull($payment->settled_at);
    }

    public function test_a_settled_payment_cannot_be_settled_again(): void
    {
        $this->actingAsStaff(StaffRole::SuperAdmin);
        $payment = EscrowPayment::factory()->create(['status' => EscrowStatus::Refunded, 'settled_at' => now()]);

        $this->post(route('dashboard.escrow.settle', $payment), ['status' => 'released', 'note' => 'changed mind'])
            ->assertSessionHasErrors('status');

        $this->assertSame(EscrowStatus::Refunded, $payment->fresh()->status);
    }

    public function test_payments_cannot_be_recorded_on_a_closed_case(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        $case = TransferCase::factory()->create(['status' => CaseStatus::Completed]);

        $this->post(route('dashboard.cases.escrow.store', $case), ['amount' => 1000, 'method' => 'cash'])
            ->assertSessionHasErrors('status');

        $this->assertSame(0, EscrowPayment::query()->where('case_id', $case->id)->count());
    }

    public function test_sales_staff_without_money_permissions_cannot_record_payments(): void
    {
        $this->actingAsStaff(StaffRole::Sales);
        $case = TransferCase::factory()->create();

        $this->post(route('dashboard.cases.escrow.store', $case), ['amount' => 1000, 'method' => 'cash'])
            ->assertForbidden();
    }

    public function test_case_page_shows_the_escrow_section_to_finance(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        $case = TransferCase::factory()->create();
        EscrowPayment::factory()->create(['case_id' => $case->id, 'reference' => 'REF-42']);

        $this->get(route('dashboard.cases.show', $case))
            ->assertOk()
            ->assertSee('data-section="escrow"', false)
            ->assertSee('REF-42');
    }
}
