<?php

namespace Tests\Feature\AthleteProfile;

use App\Models\AthleteProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreMvcCalibrationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_athlete_can_upsert_calibrations_and_calibrated_at_is_updated(): void
    {
        $user = User::factory()->create();
        $profile = AthleteProfile::factory()->create([
            'user_id' => $user->id,
            'calibrated_at' => null,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/mvc', [
            'calibrations' => [
                ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT', 'mvc_value' => 83.5],
                ['muscle' => 'VASTUS_LATERALIS', 'side' => 'RIGHT', 'mvc_value' => 79.2],
                ['muscle' => 'GLUTEUS_MAXIMUS', 'side' => 'LEFT', 'mvc_value' => 91.7],
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.muscle', 'VASTUS_LATERALIS')
            ->assertJsonPath('0.side', 'LEFT')
            ->assertJsonPath('0.mvc_value', 83.5);

        $this->assertDatabaseCount('mvc_calibrations', 3);
        $this->assertNotNull($profile->fresh()->calibrated_at);
    }

    public function test_repeated_post_upserts_in_place_no_duplicates(): void
    {
        $user = User::factory()->create();
        $profile = AthleteProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $payload = [
            'calibrations' => [
                ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT', 'mvc_value' => 55.5],
            ],
        ];

        $this->postJson('/api/athlete/mvc', $payload)->assertOk();
        $this->assertDatabaseCount('mvc_calibrations', 1);

        // Re-post with a new value
        $payload['calibrations'][0]['mvc_value'] = 95.5;
        $this->postJson('/api/athlete/mvc', $payload)->assertOk();

        $this->assertDatabaseCount('mvc_calibrations', 1); // upsert, not insert
        $this->assertDatabaseHas('mvc_calibrations', [
            'athlete_profile_id' => $profile->id,
            'muscle' => 'VASTUS_LATERALIS',
            'side' => 'LEFT',
            'mvc_value' => 95.5,
        ]);
    }

    public function test_returns_422_when_no_profile_exists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/mvc', [
            'calibrations' => [
                ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT', 'mvc_value' => 83.5],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('profile');
    }

    public function test_rejects_empty_calibrations_array(): void
    {
        $user = User::factory()->create();
        AthleteProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/mvc', [
            'calibrations' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('calibrations');
    }

    public function test_rejects_invalid_muscle_enum(): void
    {
        $user = User::factory()->create();
        AthleteProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/mvc', [
            'calibrations' => [
                ['muscle' => 'BICEPS_BRACHII', 'side' => 'LEFT', 'mvc_value' => 50.5],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('calibrations.0.muscle');
    }

    public function test_rejects_invalid_side_enum(): void
    {
        $user = User::factory()->create();
        AthleteProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/mvc', [
            'calibrations' => [
                ['muscle' => 'VASTUS_LATERALIS', 'side' => 'CENTER', 'mvc_value' => 50.5],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('calibrations.0.side');
    }

    public function test_rejects_non_positive_mvc_value(): void
    {
        $user = User::factory()->create();
        AthleteProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/mvc', [
            'calibrations' => [
                ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT', 'mvc_value' => 0],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('calibrations.0.mvc_value');
    }

    public function test_rejects_mvc_value_above_100(): void
    {
        $user = User::factory()->create();
        AthleteProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/mvc', [
            'calibrations' => [
                ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT', 'mvc_value' => 150],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('calibrations.0.mvc_value');
    }

    public function test_accepts_boundary_value_100(): void
    {
        $user = User::factory()->create();
        AthleteProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/mvc', [
            'calibrations' => [
                ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT', 'mvc_value' => 100],
            ],
        ]);

        $response->assertOk();
    }

    public function test_instructor_cannot_post_calibrations(): void
    {
        $instructor = User::factory()->instructor()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/athlete/mvc', [
            'calibrations' => [
                ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT', 'mvc_value' => 50.5],
            ],
        ]);

        $response->assertForbidden();
    }
}
