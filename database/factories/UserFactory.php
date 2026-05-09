<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => Role::firstOrCreate(['name' => UserRole::ATHLETE->value])->id,
        ];
    }

    public function instructor(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::firstOrCreate(['name' => UserRole::INSTRUCTOR->value])->id,
        ]);
    }
}
