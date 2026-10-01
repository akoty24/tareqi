<?php

namespace Database\Factories;

use App\Models\Role;
use App\Enums\UserStatus;
use Database\Factories\Support\EgyptianData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => EgyptianData::name(),
            'phone' => EgyptianData::phone(),
            'email' => fake()->unique()->userName().fake()->numberBetween(10, 999).'@example.com',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role_id' => Role::superAdmin()->id]);
    }

    /** Staff member with a specific (usually limited) role. */
    public function withRole(Role $role): static
    {
        return $this->state(fn () => ['role_id' => $role->id]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Blocked, 'blocked_at' => now()]);
    }
}
