<?php

namespace Tests\Feature\AthleteProfile;

use App\Models\AthleteProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_athlete_can_patch_partial_profile(): void
    {
        $user = User::factory()->create();
        $profile = AthleteProfile::factory()->create([
            'user_id' => $user->id,
            'bodyweight_kg' => 82.5,
            'age_years' => 28,
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/athlete/profile', [
            'bodyweight_kg' => 85.5,
        ]);

        $response->assertOk()
            ->assertJsonPath('bodyweight_kg', 85.5)
            ->assertJsonPath('age_years', 28); // unchanged

        $this->assertDatabaseHas('athlete_profiles', [
            'id' => $profile->id,
            'bodyweight_kg' => 85.5,
        ]);
    }

    public function test_empty_patch_body_is_a_noop(): void
    {
        $user = User::factory()->create();
        $profile = AthleteProfile::factory()->create([
            'user_id' => $user->id,
            'bodyweight_kg' => 82.5,
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/athlete/profile', []);

        $response->assertOk()
            ->assertJsonPath('bodyweight_kg', 82.5);
    }

    public function test_patch_without_existing_profile_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/athlete/profile', [
            'bodyweight_kg' => 85.0,
        ]);

        $response->assertNotFound();
    }

    public function test_patch_validates_ranges(): void
    {
        $user = User::factory()->create();
        AthleteProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/athlete/profile', [
            'bodyweight_kg' => 1000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('bodyweight_kg');
    }

    public function test_instructor_cannot_patch_athlete_profile(): void
    {
        $instructor = User::factory()->instructor()->create();
        Sanctum::actingAs($instructor);

        $response = $this->patchJson('/api/athlete/profile', [
            'bodyweight_kg' => 85.0,
        ]);

        $response->assertForbidden();
    }
}
