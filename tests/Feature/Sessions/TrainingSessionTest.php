<?php

namespace Tests\Feature\Sessions;

use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingSessionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_athlete_lists_only_their_own_sessions_paginated_desc(): void
    {
        $athlete = User::factory()->create();
        $other = User::factory()->create();

        TrainingSession::factory()->count(3)->create([
            'athlete_user_id' => $athlete->id,
            'started_at' => now()->subDays(2),
        ]);
        TrainingSession::factory()->create([
            'athlete_user_id' => $athlete->id,
            'started_at' => now(),
        ]);
        TrainingSession::factory()->count(2)->create([
            'athlete_user_id' => $other->id,
        ]);

        Sanctum::actingAs($athlete);

        $response = $this->getJson('/api/sessions');

        $response->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'exercise', 'started_at', 'ended_at', 'device_source']],
                'links',
                'meta',
            ]);
    }

    public function test_athlete_creates_session_with_minimal_body(): void
    {
        $athlete = User::factory()->create();
        Sanctum::actingAs($athlete);

        $response = $this->postJson('/api/sessions', [
            'started_at' => now()->toIso8601String(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('exercise', 'back_squat')
            ->assertJsonPath('device_source', 'SIMULATED')
            ->assertJsonPath('ended_at', null);

        $this->assertDatabaseHas('training_sessions', [
            'athlete_user_id' => $athlete->id,
            'exercise' => 'back_squat',
        ]);
    }

    public function test_athlete_creates_session_with_explicit_fields(): void
    {
        $athlete = User::factory()->create();
        Sanctum::actingAs($athlete);

        $response = $this->postJson('/api/sessions', [
            'exercise' => 'front_squat',
            'started_at' => '2026-05-10T15:00:00Z',
            'device_source' => 'REAL',
        ]);

        $response->assertCreated()
            ->assertJsonPath('exercise', 'front_squat')
            ->assertJsonPath('device_source', 'REAL');
    }

    public function test_store_rejects_missing_started_at(): void
    {
        $athlete = User::factory()->create();
        Sanctum::actingAs($athlete);

        $this->postJson('/api/sessions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('started_at');
    }

    public function test_store_rejects_invalid_device_source(): void
    {
        $athlete = User::factory()->create();
        Sanctum::actingAs($athlete);

        $this->postJson('/api/sessions', [
            'started_at' => now()->toIso8601String(),
            'device_source' => 'FAKE',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_source');
    }

    public function test_athlete_ends_their_session(): void
    {
        $athlete = User::factory()->create();
        $session = TrainingSession::factory()->create([
            'athlete_user_id' => $athlete->id,
            'started_at' => now()->subMinutes(60),
        ]);
        Sanctum::actingAs($athlete);

        $endedAt = now()->toIso8601String();
        $response = $this->putJson("/api/sessions/{$session->id}/end", [
            'ended_at' => $endedAt,
        ]);

        $response->assertOk()
            ->assertJsonPath('id', $session->id);
        $this->assertNotNull($session->fresh()->ended_at);
    }

    public function test_end_is_idempotent_on_already_ended_session(): void
    {
        $athlete = User::factory()->create();
        $session = TrainingSession::factory()->ended()->create([
            'athlete_user_id' => $athlete->id,
        ]);
        Sanctum::actingAs($athlete);

        $this->putJson("/api/sessions/{$session->id}/end", [
            'ended_at' => now()->toIso8601String(),
        ])->assertOk();
    }

    public function test_end_rejects_missing_ended_at(): void
    {
        $athlete = User::factory()->create();
        $session = TrainingSession::factory()->create([
            'athlete_user_id' => $athlete->id,
        ]);
        Sanctum::actingAs($athlete);

        $this->putJson("/api/sessions/{$session->id}/end", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ended_at');
    }

    public function test_athlete_cannot_end_another_athletes_session(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $session = TrainingSession::factory()->create([
            'athlete_user_id' => $b->id,
        ]);
        Sanctum::actingAs($a);

        $this->putJson("/api/sessions/{$session->id}/end", [
            'ended_at' => now()->toIso8601String(),
        ])->assertNotFound();
    }

    public function test_instructor_cannot_access_session_endpoints(): void
    {
        $instructor = User::factory()->instructor()->create();
        Sanctum::actingAs($instructor);

        $this->getJson('/api/sessions')->assertForbidden();
        $this->postJson('/api/sessions', [
            'started_at' => now()->toIso8601String(),
        ])->assertForbidden();
        $this->putJson('/api/sessions/1/end', [
            'ended_at' => now()->toIso8601String(),
        ])->assertForbidden();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/sessions')->assertUnauthorized();
    }
}
