<?php

namespace Tests\Feature\AthleteProfile;

use App\Models\AthleteProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_athlete_can_create_their_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/profile', [
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'bodyweight_kg' => 82.5,
            'age_years' => 28,
            'sex' => 'MALE',
        ]);

        $response->assertCreated()
            ->assertJsonPath('first_name', 'Juan')
            ->assertJsonPath('bodyweight_kg', 82.5)
            ->assertJsonPath('sex', 'MALE');

        $this->assertDatabaseHas('athlete_profiles', [
            'user_id' => $user->id,
            'first_name' => 'Juan',
        ]);
    }

    public function test_creating_a_second_profile_returns_422(): void
    {
        $user = User::factory()->create();
        AthleteProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/profile', [
            'first_name' => 'Other',
            'last_name' => 'Person',
            'bodyweight_kg' => 70,
            'age_years' => 25,
            'sex' => 'FEMALE',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('profile');
    }

    public function test_instructor_cannot_create_athlete_profile(): void
    {
        $instructor = User::factory()->instructor()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/athlete/profile', [
            'first_name' => 'X',
            'last_name' => 'Y',
            'bodyweight_kg' => 70,
            'age_years' => 30,
            'sex' => 'MALE',
        ]);

        $response->assertForbidden();
    }

    public function test_rejects_missing_required_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/profile', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'first_name', 'last_name', 'bodyweight_kg', 'age_years', 'sex',
            ]);
    }

    public function test_rejects_invalid_sex(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/profile', [
            'first_name' => 'X',
            'last_name' => 'Y',
            'bodyweight_kg' => 70,
            'age_years' => 30,
            'sex' => 'OTHER',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('sex');
    }

    public function test_rejects_bodyweight_out_of_range(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/profile', [
            'first_name' => 'X',
            'last_name' => 'Y',
            'bodyweight_kg' => 500,
            'age_years' => 30,
            'sex' => 'MALE',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('bodyweight_kg');
    }

    public function test_rejects_age_out_of_range(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/athlete/profile', [
            'first_name' => 'X',
            'last_name' => 'Y',
            'bodyweight_kg' => 70,
            'age_years' => 5,
            'sex' => 'MALE',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('age_years');
    }
}
