<?php

namespace Database\Factories;

use App\Models\ClaimCode;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClaimCode>
 */
class ClaimCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => TrainingSession::factory(),
            'code' => strtoupper(fake()->bothify('????####')),
            'expires_at' => now()->addMinutes(5),
            'used_at' => null,
            'used_by_user_id' => null,
            'created_at' => now(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function used(?int $userId = null): static
    {
        return $this->state(fn () => [
            'used_at' => now(),
            'used_by_user_id' => $userId,
        ]);
    }
}
