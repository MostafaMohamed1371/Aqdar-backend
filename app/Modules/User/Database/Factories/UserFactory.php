<?php

namespace App\Modules\User\Database\Factories;

use App\Core\Enums\AccountType;
use App\Core\Enums\IdentityStatus;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => '+2010'.fake()->unique()->numerify('########'),
            'phone_verified_at' => now(),
            'account_type' => AccountType::Individual,
            'is_buyer' => true,
            'is_seller' => false,
            'identity_status' => IdentityStatus::Unverified,
            'locale' => 'ar',
            'consent_privacy_at' => now(),
            'consent_terms_at' => now(),
            'consent_version' => '1',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
            'identity_status' => IdentityStatus::Unverified,
        ]);
    }

    public function seller(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_seller' => true,
        ]);
    }

    public function suspended(?string $reason = 'test'): static
    {
        return $this->state(fn (array $attributes) => [
            'suspended_at' => now(),
            'suspend_reason' => $reason,
        ]);
    }
}
