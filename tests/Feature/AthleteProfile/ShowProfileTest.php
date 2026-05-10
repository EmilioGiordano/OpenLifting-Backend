<?php

namespace Tests\Feature\AthleteProfile;

use App\Models\AthleteProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShowProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_athlete_can_get_their_own_profile(): void
    {
        $user = User::factory()->create();
        $profile = AthleteProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/athlete/profile');

        $response->assertOk()
            ->assertJsonStructure(['id', 'first_name', 'last_name', 'bodyweight_kg', 'age_years', 'sex', 'calibrated_at'])
            ->assertJsonPath('id', $profile->id)
            ->assertJsonPath('first_name', $profile->first_name);
    }

    public function test_returns_404_when_no_profile_exists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/athlete/profile');

        $response->assertNotFound()
            ->assertJsonPath('message', 'No tenés perfil de atleta creado todavía.');
    }

    public function test_instructor_cannot_access_athlete_profile_endpoint(): void
    {
        $instructor = User::factory()->instructor()->create();
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/athlete/profile');

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/athlete/profile');

        $response->assertUnauthorized();
    }
}
