<?php

namespace Tests\Integration;

use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\SupportThread;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_user_opens_support_thread_with_sales_only(): void
    {
        $user = $this->marketplaceUser();

        $this->postJson('/api/v1/my/support', [
            'body' => 'Need help with my listing',
            'consent' => true,
        ])->assertCreated()
            ->assertJsonPath('data.messages.0.author', 'user')
            ->assertJsonPath('data.messages.0.body', 'Need help with my listing')
            ->assertJsonMissingPath('data.messages.0.staff_id');

        $code = SupportThread::query()->value('public_code');
        $this->assertNotNull($code);
        $this->assertStringStartsWith('SUP-', $code);

        $this->getJson('/api/v1/my/support')
            ->assertOk()
            ->assertJsonPath('data.public_code', $code)
            ->assertJsonPath('data.messages.0.author', 'user');
    }

    public function test_support_message_rejects_contact_and_other_users_are_404(): void
    {
        $user = $this->marketplaceUser();
        $this->postJson('/api/v1/my/support', [
            'body' => 'Call me on 01011112222',
            'consent' => true,
        ])->assertUnprocessable();

        $this->postJson('/api/v1/my/support', [
            'body' => 'Need help',
            'consent' => true,
        ])->assertCreated();
        $id = SupportThread::query()->value('id');

        $other = User::factory()->create(['is_buyer' => true]);
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/my/support/'.$id)->assertNotFound();
        $this->getJson('/api/v1/my/support')->assertOk()->assertJsonPath('data', null);
    }

    public function test_staff_replies_as_sales_without_exposing_staff_identity_on_api(): void
    {
        $user = $this->marketplaceUser();
        $this->postJson('/api/v1/my/support', [
            'body' => 'Need help',
            'consent' => true,
        ])->assertCreated();
        $thread = SupportThread::query()->first();

        $this->actingAsStaff();
        $this->get('/dashboard/support')
            ->assertOk()
            ->assertSee('ADM-07', false)
            ->assertSee($thread->public_code);

        $this->post(route('dashboard.support.reply', $thread), [
            'body' => 'We will call you on the Sales number',
        ])->assertRedirect();

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/my/support')
            ->assertOk()
            ->assertJsonPath('data.messages.1.author', 'sales')
            ->assertJsonPath('data.messages.1.body', 'We will call you on the Sales number')
            ->assertJsonMissingPath('data.messages.1.staff_id');
    }

    public function test_unassigned_sales_cannot_open_another_thread(): void
    {
        $owner = $this->makeStaff(StaffRole::Sales, ['email' => 'sales-owner@aqdar.test']);
        $other = $this->makeStaff(StaffRole::Sales, ['email' => 'sales-other@aqdar.test']);
        $thread = SupportThread::factory()->create(['assigned_to' => $owner->id]);

        $this->actingAs($other);
        $this->get(route('dashboard.support.show', $thread))->assertForbidden();
    }

    public function test_reviewer_cannot_open_support_desk(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $this->get('/dashboard/support')->assertForbidden();
    }

    private function marketplaceUser(): User
    {
        $user = User::factory()->create(['is_seller' => false, 'is_buyer' => true]);
        Sanctum::actingAs($user);

        return $user;
    }
}
