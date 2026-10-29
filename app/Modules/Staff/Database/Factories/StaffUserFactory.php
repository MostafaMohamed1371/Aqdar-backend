<?php

namespace App\Modules\Staff\Database\Factories;

use App\Modules\Staff\Models\Role;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<StaffUser> */
class StaffUserFactory extends Factory
{
    protected $model = StaffUser::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => Role::query()->where('slug', 'super_admin')->value('id'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(string $slug): static
    {
        return $this->state(fn (): array => [
            'role_id' => Role::query()->where('slug', $slug)->value('id'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
