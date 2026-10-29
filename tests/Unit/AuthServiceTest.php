<?php

namespace Tests\Unit;

use App\Modules\Auth\Services\AuthService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = app(AuthService::class);
    }

    #[Test]
    public function login_returns_user_and_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'unit@example.com',
            'password' => 'password123',
        ]);

        $result = $this->authService->login('unit@example.com', 'password123');

        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertSame('unit@example.com', $result['user']->email);
        $this->assertNotEmpty($result['token']);
    }

    #[Test]
    public function login_throws_for_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'unit@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(ValidationException::class);

        $this->authService->login('unit@example.com', 'wrong-password');
    }

    #[Test]
    public function logout_deletes_current_sanctum_token(): void
    {
        $user = User::factory()->create();
        $tokenResult = $user->createToken('test-token');
        $user->withAccessToken($tokenResult->accessToken);

        $this->assertSame(1, $user->tokens()->count());

        $this->authService->logout($user);

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    #[Test]
    public function register_hashes_password_via_user_service(): void
    {
        $result = $this->authService->register([
            'name' => 'Unit User',
            'email' => 'new-unit@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'consent_privacy' => true,
            'consent_terms' => true,
        ]);

        $this->assertTrue(Hash::check('password123', $result['user']->password));
    }
}
