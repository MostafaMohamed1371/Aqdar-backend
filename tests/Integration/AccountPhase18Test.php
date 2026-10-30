<?php

namespace Tests\Integration;

use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class AccountPhase18Test extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_guest_cannot_request_account_closure(): void
    {
        $this->postJson('/api/v1/me/close', ['reason' => 'أريد إغلاق الحساب'])->assertUnauthorized();
    }

    public function test_close_request_archives_public_listings_and_revokes_tokens(): void
    {
        $user = User::factory()->seller()->create([
            'email' => 'close-me@example.com',
            'password' => 'password',
            'phone' => '+201011122233',
        ]);
        $listing = Listing::factory()->published()->create(['user_id' => $user->id]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/me/close', ['reason' => 'أريد إغلاق الحساب'])
            ->assertOk()
            ->assertJsonPath('message', __('aqdar.auth.close_requested'))
            ->assertJsonPath('data.is_close_pending', true)
            ->assertJsonPath('data.is_closed', false);

        $this->assertSame(ListingStatus::Archived, $listing->fresh()->status);
        $this->assertNotNull($user->fresh()->close_requested_at);
        $this->assertNull($user->fresh()->closed_at);
        $this->assertSame(0, $user->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'close-me@example.com',
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/auth/otp/send', $this->otpSendBody('+201011122233'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_staff_complete_anonymizes_and_keeps_the_row(): void
    {
        $user = User::factory()->create([
            'name' => 'Mona Ali',
            'email' => 'mona-close@example.com',
            'phone' => '+201044455566',
        ]);
        $user->forceFill([
            'close_requested_at' => now(),
            'close_reason' => 'أريد إغلاق الحساب',
        ])->save();

        $this->actingAsStaff(StaffRole::Operations);
        $this->from('/dashboard/users')
            ->post('/dashboard/users/'.$user->id.'/close/complete')
            ->assertRedirect();

        $closed = $user->fresh();
        $this->assertNotNull($closed);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame('حساب مغلق', $closed->name);
        $this->assertSame('closed.'.$user->id.'@invalid.local', $closed->email);
        $this->assertNull($closed->phone);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'mona-close@example.com',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_staff_can_decline_a_pending_closure(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'close_requested_at' => now(),
            'close_reason' => 'أريد إغلاق الحساب',
        ])->save();

        $this->actingAsStaff(StaffRole::Operations);
        $this->from('/dashboard/users')
            ->post('/dashboard/users/'.$user->id.'/close/decline')
            ->assertRedirect();

        $this->assertNull($user->fresh()->close_requested_at);
        $this->assertNull($user->fresh()->closed_at);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_sales_cannot_complete_or_decline_closure(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['close_requested_at' => now(), 'close_reason' => 'سبب'])->save();

        $this->actingAsStaff(StaffRole::Sales);
        $this->post('/dashboard/users/'.$user->id.'/close/complete')->assertForbidden();
        $this->post('/dashboard/users/'.$user->id.'/close/decline')->assertForbidden();
        $this->assertNotNull($user->fresh()->close_requested_at);
    }

    public function test_marketplace_routes_reject_a_pending_close_session(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['close_requested_at' => now(), 'close_reason' => 'سبب'])->save();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me', ['name' => 'New Name'])
            ->assertForbidden();
    }

    public function test_close_requires_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/me/close', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_get_me_exposes_pending_close_fields(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['close_requested_at' => now(), 'close_reason' => 'سبب'])->save();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.is_close_pending', true)
            ->assertJsonPath('data.is_closed', false)
            ->assertJsonPath('data.is_suspended', false);
    }

    public function test_adm04_shows_pending_close_badge_and_actions(): void
    {
        $user = User::factory()->create(['name' => 'Pending Close User']);
        $user->forceFill([
            'close_requested_at' => now(),
            'close_reason' => 'أريد إغلاق الحساب',
        ])->save();

        $this->actingAsStaff(StaffRole::Operations);
        $this->get('/dashboard/users')
            ->assertOk()
            ->assertSee(__('aqdar.users.close_requested_badge'), false)
            ->assertSee(__('aqdar.users.complete_close'), false)
            ->assertSee(__('aqdar.users.decline_close'), false)
            ->assertSee('Pending Close User', false);
    }
}
