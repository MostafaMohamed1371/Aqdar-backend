<?php

namespace Tests\Integration;

use App\Core\Enums\StaffRole;
use App\Core\Enums\SupportAuthorType;
use App\Modules\Crm\Events\SupportDeskUpdated;
use App\Modules\Crm\Events\SupportMessageSent;
use App\Modules\Crm\Events\SupportThreadClosed;
use App\Modules\Crm\Models\SupportMessage;
use App\Modules\Crm\Models\SupportThread;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class SupportRealtimeFallbackTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_staff_can_poll_new_support_messages_without_pusher(): void
    {
        Event::fake([SupportMessageSent::class, SupportThreadClosed::class, SupportDeskUpdated::class]);

        $this->marketplaceUser();
        $this->postJson('/api/v1/my/support', [
            'body' => 'Need help now',
            'consent' => true,
        ])->assertCreated();

        $thread = SupportThread::query()->firstOrFail();
        $firstId = (int) SupportMessage::query()->where('thread_id', $thread->id)->value('id');

        SupportMessage::query()->create([
            'thread_id' => $thread->id,
            'author_type' => SupportAuthorType::User,
            'body' => 'Second message',
            'created_at' => now(),
        ]);

        $this->actingAsStaff(StaffRole::SuperAdmin);

        $this->getJson(route('dashboard.support.poll', ['supportThread' => $thread, 'after_id' => $firstId]))
            ->assertOk()
            ->assertJsonPath('data.thread_id', $thread->id)
            ->assertJsonPath('data.messages.0.body', 'Second message')
            ->assertJsonPath('data.messages.0.author', 'user');
    }

    public function test_staff_reply_still_succeeds_when_broadcast_throws(): void
    {
        Event::fake([SupportMessageSent::class, SupportThreadClosed::class, SupportDeskUpdated::class]);

        $this->marketplaceUser();
        $this->postJson('/api/v1/my/support', [
            'body' => 'Need help',
            'consent' => true,
        ])->assertCreated();

        $thread = SupportThread::query()->firstOrFail();

        Event::listen(SupportMessageSent::class, function (): void {
            throw new \RuntimeException('Failed to create broadcaster for connection "pusher"');
        });
        Event::listen(SupportDeskUpdated::class, function (): void {
            throw new \RuntimeException('Failed to create broadcaster for connection "pusher"');
        });

        $this->actingAsStaff(StaffRole::SuperAdmin);

        $this->post(route('dashboard.support.reply', $thread), [
            'body' => 'We will help you',
        ])->assertRedirect();

        $this->assertDatabaseHas('support_messages', [
            'thread_id' => $thread->id,
            'body' => 'We will help you',
        ]);
    }

    private function marketplaceUser(): User
    {
        $user = User::factory()->create(['is_seller' => false, 'is_buyer' => true]);
        Sanctum::actingAs($user);

        return $user;
    }
}
