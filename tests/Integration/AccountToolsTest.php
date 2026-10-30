<?php

namespace Tests\Integration;

use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_slots_endpoint_lists_configured_slots_and_hides_past_ones(): void
    {
        config(['aqdar.appointments.slots' => ['09:00', '18:00'], 'aqdar.appointments.closed_weekdays' => []]);
        $this->travelTo(now()->startOfDay()->setTime(12, 0));
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/appointments/slots?date='.today()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.open', true)
            ->assertJsonPath('data.slots', [
                ['value' => '09:00', 'available' => false],
                ['value' => '18:00', 'available' => true],
            ]);

        $this->getJson('/api/v1/appointments/slots?date='.today()->subDay()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.open', false);
    }

    public function test_booking_rejects_unknown_or_closed_day_slots(): void
    {
        config(['aqdar.appointments.slots' => ['10:30'], 'aqdar.appointments.closed_weekdays' => [now()->addDays(2)->dayOfWeek]]);
        $listing = Listing::factory()->published()->create();
        Sanctum::actingAs(User::factory()->create(['is_buyer' => true]));

        $payload = ['listing_id' => $listing->id, 'consent' => true];

        $this->postJson('/api/v1/appointments', $payload + [
            'requested_date' => now()->addDay()->toDateString(),
            'requested_slot' => '23:59',
        ])->assertUnprocessable()->assertJsonValidationErrors('requested_slot');

        $this->postJson('/api/v1/appointments', $payload + [
            'requested_date' => now()->addDays(2)->toDateString(),
            'requested_slot' => '10:30',
        ])->assertUnprocessable()->assertJsonValidationErrors('requested_slot');

        $this->postJson('/api/v1/appointments', $payload + [
            'requested_date' => now()->addDay()->toDateString(),
            'requested_slot' => '10:30',
        ])->assertCreated();
    }

    public function test_user_changes_password_and_other_devices_are_signed_out(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123!']);
        $current = $user->createToken('phone')->plainTextToken;
        $user->createToken('tablet');

        $this->withToken($current)->postJson('/api/v1/me/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->withToken($current)->postJson('/api/v1/me/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertSame(['phone'], $user->tokens()->pluck('name')->all());
    }

    public function test_otp_only_user_sets_first_password_without_current_one(): void
    {
        $user = User::factory()->create(['password' => null]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/password', [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_two_factor_login_requires_phone_otp_after_password(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'phone' => '01012345678',
            'phone_verified_at' => now(),
            'two_factor_enabled' => true,
        ]);

        $challenge = $this->postJson('/api/v1/auth/login', ['email' => 'buyer@example.com', 'password' => 'password'])
            ->assertStatus(202)
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonMissingPath('token')
            ->json('challenge');

        $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $challenge, 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $challenge, 'code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.two_factor_enabled', true);

        $this->postJson('/api/v1/auth/login/two-factor', ['challenge' => $challenge, 'code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge');
    }

    public function test_user_toggles_two_factor_with_password_and_verified_phone(): void
    {
        $unverified = User::factory()->create(['phone' => null]);
        Sanctum::actingAs($unverified);
        $this->putJson('/api/v1/me/two-factor', ['enabled' => true, 'current_password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('enabled');

        $user = User::factory()->create(['phone' => '01012345679', 'phone_verified_at' => now()]);
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/me/two-factor', ['enabled' => true, 'current_password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->putJson('/api/v1/me/two-factor', ['enabled' => true, 'current_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true)
            ->assertJsonPath('data.region.code', 'EG');

        $this->assertTrue($user->fresh()->two_factor_enabled);
    }
}
