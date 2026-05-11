<?php

namespace Database\Factories;

use App\Enums\Sex;
use App\Models\GuestProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestProfile>
 */
class GuestProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by_user_id' => User::factory()->instructor(),
            'claimed_by_user_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'bodyweight_kg' => fake()->randomFloat(2, 60, 110),
            'age_years' => fake()->numberBetween(18, 45),
            'sex' => fake()->randomElement(Sex::cases())->value,
            'calibrated_at' => null,
            'claimed_at' => null,
        ];
    }

    public function claimedBy(User $user): static
    {
        return $this->state(fn () => [
            'claimed_by_user_id' => $user->id,
            'claimed_at' => now(),
        ]);
    }
}
