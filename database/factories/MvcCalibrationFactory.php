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
            // %MVC in (0, 100]; realistic captures fall in [70, 99] due to
            // neural inhibition during voluntary maximal contractions.
            'mvc_value' => fake()->randomFloat(2, 70, 99),
            'recorded_at' => now(),
        ];
    }
}
