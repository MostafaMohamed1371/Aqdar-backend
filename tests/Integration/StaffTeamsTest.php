<?php

namespace Tests\Integration;

use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Setting\Services\SettingService;
use App\Modules\Staff\Models\StaffTeam;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class StaffTeamsTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_super_admin_creates_updates_and_retires_a_team(): void
    {
        $this->actingAsStaff();

        $this->post(route('dashboard.staff-teams.store'), [
            'slug' => 'cairo-sales',
            'name_ar' => 'مبيعات القاهرة',
            'name_en' => 'Cairo Sales',
            'sales_phone' => '+20 100 123 4567',
        ])->assertRedirect(route('dashboard.staff.index'))->assertSessionHas('status');

        $team = StaffTeam::query()->where('slug', 'cairo-sales')->firstOrFail();

        $this->put(route('dashboard.staff-teams.update', $team), [
            'slug' => 'cairo-sales',
            'name_ar' => 'مبيعات القاهرة',
            'name_en' => 'Cairo Sales Team',
            'sales_phone' => '',
        ])->assertRedirect(route('dashboard.staff.index'));

        $this->assertDatabaseHas('staff_teams', ['id' => $team->id, 'name_en' => 'Cairo Sales Team', 'sales_phone' => null]);

        $this->delete(route('dashboard.staff-teams.destroy', $team))->assertRedirect(route('dashboard.staff.index'));
        $this->assertDatabaseHas('staff_teams', ['id' => $team->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.team_retired']);

        $this->get(route('dashboard.staff.index'))->assertOk()->assertSee('Cairo Sales Team');
    }

    public function test_only_staff_managers_can_manage_teams(): void
    {
        $this->actingAsStaff(StaffRole::Sales);

        $this->post(route('dashboard.staff-teams.store'), [
            'slug' => 'nope',
            'name_ar' => 'x',
            'name_en' => 'x',
        ])->assertForbidden();

        $this->assertDatabaseCount('staff_teams', 0);
    }

    public function test_invalid_team_phone_is_rejected(): void
    {
        $this->actingAsStaff();

        $this->post(route('dashboard.staff-teams.store'), [
            'slug' => 'bad-phone',
            'name_ar' => 'x',
            'name_en' => 'x',
            'sales_phone' => 'call-me-maybe',
        ])->assertSessionHasErrors('sales_phone');
    }

    public function test_staff_can_be_assigned_to_an_active_team_only(): void
    {
        $this->actingAsStaff();
        $active = StaffTeam::factory()->create();
        $retired = StaffTeam::factory()->create(['is_active' => false]);
        $member = $this->makeStaff(StaffRole::Sales);
        $payload = ['name' => $member->name, 'email' => $member->email, 'role_id' => $member->role_id];

        $this->put(route('dashboard.staff.update', $member), $payload + ['team_id' => $retired->id])
            ->assertSessionHasErrors('team_id');

        $this->put(route('dashboard.staff.update', $member), $payload + ['team_id' => $active->id])
            ->assertRedirect(route('dashboard.staff.index'));

        $this->assertSame($active->id, $member->fresh()->team_id);
    }

    public function test_sales_contact_uses_the_assignee_team_number_and_falls_back_to_the_platform(): void
    {
        $settings = app(SettingService::class);
        $platform = $settings->publicConfig()['sales_contact'];

        $team = StaffTeam::factory()->withSalesNumber('+20 111 222 3333')->create(['sales_hours_en' => '9-5']);
        $member = $this->makeStaff(StaffRole::Sales, ['team_id' => $team->id]);
        $loner = $this->makeStaff(StaffRole::Sales);

        $this->assertSame('+20 111 222 3333', $settings->salesContactFor($member)['phone']);
        $this->assertSame($platform, $settings->salesContactFor($loner));
        $this->assertSame($platform, $settings->salesContactFor(null));

        $team->update(['is_active' => false]);
        $this->assertSame($platform, $settings->salesContactFor($member->fresh()));

        $teamWithoutNumber = StaffTeam::factory()->create();
        $other = $this->makeStaff(StaffRole::Sales, ['team_id' => $teamWithoutNumber->id]);
        $this->assertSame($platform, $settings->salesContactFor($other));
    }

    public function test_buyer_request_api_shows_the_assigned_team_number(): void
    {
        $team = StaffTeam::factory()->withSalesNumber('+20 122 333 4444')->create();
        /** @var StaffUser $member */
        $member = $this->makeStaff(StaffRole::Sales, ['team_id' => $team->id]);
        $buyer = User::factory()->create(['is_buyer' => true]);
        Sanctum::actingAs($buyer);

        $id = $this->postJson('/api/v1/buyer-requests', [
            'intent' => 'living',
            'contact_method' => 'phone',
            'consent' => true,
            'budget_max' => 2000000,
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/my/buyer-requests/{$id}")
            ->assertJsonPath('data.sales_contact.phone', config('aqdar.sales.phone'));

        BuyerRequest::query()->whereKey($id)->update(['assigned_to' => $member->id]);

        $this->getJson("/api/v1/my/buyer-requests/{$id}")
            ->assertOk()
            ->assertJsonPath('data.sales_contact.phone', '+20 122 333 4444');
    }
}
