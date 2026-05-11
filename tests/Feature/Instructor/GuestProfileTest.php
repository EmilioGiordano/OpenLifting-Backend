<?php

namespace Tests\Feature\Instructor;

use App\Models\GuestProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_instructor_creates_guest_profile(): void
    {
        $instructor = User::factory()->instructor()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/instructor/guests', [
            'first_name' => 'Diego',
            'last_name' => 'Rodríguez',
            'bodyweight_kg' => 88.5,
            'age_years' => 32,
            'sex' => 'MALE',
        ]);

        $response->assertCreated()
            ->assertJsonPath('first_name', 'Diego')
            ->assertJsonPath('claimed', false)
            ->assertJsonPath('claimed_at', null);

        $this->assertDatabaseHas('guest_profiles', [
            'created_by_user_id' => $instructor->id,
            'first_name' => 'Diego',
            'claimed_by_user_id' => null,
        ]);
    }

    public function test_athlete_cannot_create_guest_profile(): void
    {
        $athlete = User::factory()->create();
        Sanctum::actingAs($athlete);

        $this->postJson('/api/instructor/guests', [
            'first_name' => 'X', 'last_name' => 'Y',
            'bodyweight_kg' => 70, 'age_years' => 25, 'sex' => 'MALE',
        ])->assertForbidden();
    }

    public function test_invalid_sex_rejected(): void
    {
        $instructor = User::factory()->instructor()->create();
        Sanctum::actingAs($instructor);

        $this->postJson('/api/instructor/guests', [
            'first_name' => 'X', 'last_name' => 'Y',
            'bodyweight_kg' => 70, 'age_years' => 25, 'sex' => 'OTHER',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sex');
    }

    public function test_index_lists_only_own_guests(): void
    {
        $instructorA = User::factory()->instructor()->create();
        $instructorB = User::factory()->instructor()->create();

        GuestProfile::factory()->count(3)->create(['created_by_user_id' => $instructorA->id]);
        GuestProfile::factory()->count(2)->create(['created_by_user_id' => $instructorB->id]);

        Sanctum::actingAs($instructorA);

        $this->getJson('/api/instructor/guests')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_calibrate_guest_creates_wide_mvc_row(): void
    {
        $instructor = User::factory()->instructor()->create();
        $guest = GuestProfile::factory()->create([
            'created_by_user_id' => $instructor->id,
            'calibrated_at' => null,
        ]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson("/api/instructor/guests/{$guest->id}/mvc", [
            'calibrations' => [
                ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT', 'mvc_value' => 85.5],
                ['muscle' => 'GLUTEUS_MAXIMUS', 'side' => 'RIGHT', 'mvc_value' => 91.2],
            ],
        ]);

        $response->assertOk()->assertJsonCount(2);

        $this->assertDatabaseCount('mvc_calibrations', 1);
        $this->assertDatabaseHas('mvc_calibrations', [
            'guest_profile_id' => $guest->id,
            'athlete_profile_id' => null,
            'vastus_lateralis_left' => 85.5,
            'gluteus_maximus_right' => 91.2,
        ]);
        $this->assertNotNull($guest->fresh()->calibrated_at);
    }

    public function test_cannot_calibrate_another_instructors_guest(): void
    {
        $instructorA = User::factory()->instructor()->create();
        $instructorB = User::factory()->instructor()->create();
        $guest = GuestProfile::factory()->create(['created_by_user_id' => $instructorB->id]);

        Sanctum::actingAs($instructorA);

        $this->postJson("/api/instructor/guests/{$guest->id}/mvc", [
            'calibrations' => [
                ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT', 'mvc_value' => 80],
            ],
        ])->assertNotFound();
    }
}
