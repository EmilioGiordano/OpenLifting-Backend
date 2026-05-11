<?php

namespace Database\Factories;

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
        $base = [
            'athlete_profile_id' => AthleteProfile::factory(),
            'guest_profile_id' => null,
            'recorded_at' => now(),
        ];

        // %MVC in (0, 100]; realistic captures fall in [70, 99] due to
        // neural inhibition during voluntary maximal contractions.
        foreach (MvcCalibration::CALIBRATION_SLOTS as $slot) {
            $base[$slot['col']] = fake()->randomFloat(2, 70, 99);
        }

        return $base;
    }
}
