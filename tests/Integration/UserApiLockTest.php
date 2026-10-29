<?php

namespace Tests\Integration;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_authenticated_user_cannot_list_or_create_users(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/users')->assertForbidden();
        $this->postJson('/api/v1/users', [
            'name' => 'Other Person',
            'email' => 'other@example.com',
            'password' => 'password123',
        ])->assertForbidden();
    }

    public function test_authenticated_user_cannot_update_or_delete_another_user(): void
    {
        $target = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/users/{$target->id}", [
            'name' => 'Hacked',
        ])->assertForbidden();

        $this->deleteJson("/api/v1/users/{$target->id}")->assertForbidden();
    }
}
