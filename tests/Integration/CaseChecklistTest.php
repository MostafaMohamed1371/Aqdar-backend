<?php

namespace Tests\Integration;

use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\CaseChecklistItem;
use App\Modules\Crm\Models\TransferCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class CaseChecklistTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_staff_can_mark_a_title_transfer_step_done_and_undo_it(): void
    {
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin);
        $case = TransferCase::factory()->create();

        $this->post(route('dashboard.cases.checklist', ['transferCase' => $case, 'item' => 'contract_signed']), ['done' => 1])
            ->assertSessionHas('status');

        $item = CaseChecklistItem::query()->where('case_id', $case->id)->where('item_key', 'contract_signed')->firstOrFail();
        $this->assertNotNull($item->done_at);
        $this->assertSame($staff->id, $item->done_by_staff_id);

        $this->post(route('dashboard.cases.checklist', ['transferCase' => $case, 'item' => 'contract_signed']), ['done' => 0]);

        $this->assertNull($item->fresh()->done_at);
        $this->assertNull($item->fresh()->done_by_staff_id);
    }

    public function test_checklist_shows_every_configured_step_on_the_case_page(): void
    {
        $this->actingAsStaff(StaffRole::SuperAdmin);
        $case = TransferCase::factory()->create();

        $this->get(route('dashboard.cases.show', $case))
            ->assertOk()
            ->assertSee('data-section="checklist"', false)
            ->assertSee(__('aqdar.checklist.items.title_transferred'));

        $this->assertSame(
            count(config('aqdar.transfer_checklist')),
            CaseChecklistItem::query()->where('case_id', $case->id)->count(),
        );
    }

    public function test_unknown_checklist_step_is_not_found(): void
    {
        $this->actingAsStaff(StaffRole::SuperAdmin);
        $case = TransferCase::factory()->create();

        $this->post(route('dashboard.cases.checklist', ['transferCase' => $case, 'item' => 'made_up_step']), ['done' => 1])
            ->assertNotFound();
    }

    public function test_finance_only_staff_cannot_change_the_checklist(): void
    {
        $this->actingAsStaff(StaffRole::Finance);
        $case = TransferCase::factory()->create();

        $this->post(route('dashboard.cases.checklist', ['transferCase' => $case, 'item' => 'contract_signed']), ['done' => 1])
            ->assertForbidden();
    }
}
