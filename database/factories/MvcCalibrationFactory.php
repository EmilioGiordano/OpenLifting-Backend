<?php

namespace Database\Factories;

use App\Enums\Muscle;
use App\Enums\MuscleSide;
use App\Models\AthleteProfile;
use App\Models\MvcCalibration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MvcCalibration>
 */
class MvcCalibrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'athlete_profile_id' => AthleteProfile::factory(),
            'muscle' => fake()->randomElement(Muscle::cases())->value,
            'side' => fake()->randomElement(MuscleSide::cases())->value,
            'mvc_value' => fake()->randomFloat(4, 0.3, 1.0),
            'recorded_at' => now(),
        ];
    }
}
