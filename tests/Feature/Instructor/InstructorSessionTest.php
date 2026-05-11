<?php

namespace Tests\Feature\Instructor;

use App\Models\ClaimCode;
use App\Models\GuestProfile;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorSessionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_instructor_creates_session_for_own_guest(): void
    {
        $instructor = User::factory()->instructor()->create();
        $guest = GuestProfile::factory()->create(['created_by_user_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/instructor/sessions', [
            'guest_profile_id' => $guest->id,
            'started_at' => now()->toIso8601String(),
            'device_source' => 'SIMULATED',
        ]);

        $response->assertCreated()
            ->assertJsonPath('exercise', 'back_squat')
            ->assertJsonPath('device_source', 'SIMULATED');

        $this->assertDatabaseHas('training_sessions', [
            'guest_profile_id' => $guest->id,
            'instructor_user_id' => $instructor->id,
            'athlete_user_id' => null,
        ]);
    }

    public function test_instructor_cannot_create_session_for_another_instructors_guest(): void
    {
        $instructorA = User::factory()->instructor()->create();
        $instructorB = User::factory()->instructor()->create();
        $guest = GuestProfile::factory()->create(['created_by_user_id' => $instructorB->id]);
        Sanctum::actingAs($instructorA);

        $this->postJson('/api/instructor/sessions', [
            'guest_profile_id' => $guest->id,
            'started_at' => now()->toIso8601String(),
        ])->assertNotFound();
    }

    public function test_cannot_create_session_for_claimed_guest(): void
    {
        $instructor = User::factory()->instructor()->create();
        $athlete = User::factory()->create();
        $guest = GuestProfile::factory()->claimedBy($athlete)->create([
            'created_by_user_id' => $instructor->id,
        ]);
        Sanctum::actingAs($instructor);

        $this->postJson('/api/instructor/sessions', [
            'guest_profile_id' => $guest->id,
            'started_at' => now()->toIso8601String(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guest_profile_id');
    }

    public function test_athlete_cannot_use_instructor_session_endpoint(): void
    {
        $athlete = User::factory()->create();
        Sanctum::actingAs($athlete);

        $this->postJson('/api/instructor/sessions', [
            'guest_profile_id' => 1,
            'started_at' => now()->toIso8601String(),
        ])->assertForbidden();
    }

    public function test_generates_claim_code_for_guest_session(): void
    {
        $instructor = User::factory()->instructor()->create();
        $guest = GuestProfile::factory()->create(['created_by_user_id' => $instructor->id]);
        $session = TrainingSession::factory()->create([
            'athlete_user_id' => null,
            'guest_profile_id' => $guest->id,
            'instructor_user_id' => $instructor->id,
        ]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson("/api/instructor/sessions/{$session->id}/claim-code");

        $response->assertCreated()
            ->assertJsonStructure(['code', 'session_id', 'expires_at']);

        $this->assertEquals(8, strlen($response->json('code')));
        $this->assertDatabaseHas('claim_codes', [
            'session_id' => $session->id,
            'code' => $response->json('code'),
            'used_at' => null,
        ]);
    }

    public function test_generating_new_code_invalidates_previous_active_one(): void
    {
        $instructor = User::factory()->instructor()->create();
        $guest = GuestProfile::factory()->create(['created_by_user_id' => $instructor->id]);
        $session = TrainingSession::factory()->create([
            'athlete_user_id' => null,
            'guest_profile_id' => $guest->id,
            'instructor_user_id' => $instructor->id,
        ]);
        Sanctum::actingAs($instructor);

        $first = $this->postJson("/api/instructor/sessions/{$session->id}/claim-code")->json('code');
        $second = $this->postJson("/api/instructor/sessions/{$session->id}/claim-code")->json('code');

        $this->assertNotEquals($first, $second);

        // El primero quedó expirado (expires_at <= now())
        $firstRow = ClaimCode::where('code', $first)->first();
        $this->assertTrue($firstRow->expires_at->lessThanOrEqualTo(now()));

        $secondRow = ClaimCode::where('code', $second)->first();
        $this->assertTrue($secondRow->expires_at->isFuture());
    }

    public function test_cannot_generate_code_for_another_instructors_session(): void
    {
        $instructorA = User::factory()->instructor()->create();
        $instructorB = User::factory()->instructor()->create();
        $guest = GuestProfile::factory()->create(['created_by_user_id' => $instructorB->id]);
        $session = TrainingSession::factory()->create([
            'athlete_user_id' => null,
            'guest_profile_id' => $guest->id,
            'instructor_user_id' => $instructorB->id,
        ]);
        Sanctum::actingAs($instructorA);

        $this->postJson("/api/instructor/sessions/{$session->id}/claim-code")
            ->assertNotFound();
    }
}
