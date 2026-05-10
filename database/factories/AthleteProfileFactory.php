<?php

namespace Database\Factories;

use App\Enums\Sex;
use App\Models\AthleteProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AthleteProfile>
 */
class AthleteProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'bodyweight_kg' => fake()->randomFloat(2, 60, 110),
            'age_years' => fake()->numberBetween(18, 45),
            'sex' => fake()->randomElement(Sex::cases())->value,
            'calibrated_at' => null,
        ];
    }
}
