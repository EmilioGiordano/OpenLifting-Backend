<?php

namespace Database\Factories;

use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingSession>
 */
class TrainingSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'athlete_user_id' => User::factory(),
            'instructor_user_id' => null,
            'exercise' => 'back_squat',
            'started_at' => now()->subMinutes(60),
            'ended_at' => null,
            'device_source' => 'SIMULATED',
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'ended_at' => now(),
        ]);
    }
}
