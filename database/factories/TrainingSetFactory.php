<?php

namespace Database\Factories;

use App\Models\TrainingSession;
use App\Models\TrainingSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingSet>
 */
class TrainingSetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => TrainingSession::factory(),
            'set_number' => 1,
            'load_kg' => 100.0,
            'target_reps' => 5,
            'variant' => 'LOW_BAR',
            'depth' => 'PARALLEL',
            'rpe' => 8.0,
        ];
    }
}
