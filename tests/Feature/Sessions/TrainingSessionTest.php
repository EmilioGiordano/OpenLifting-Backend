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

    public function test_instructor_cannot_access_athlete_only_session_endpoints(): void
    {
        $instructor = User::factory()->instructor()->create();
        Sanctum::actingAs($instructor);

        // Index y store de sesiones siguen siendo athlete-only (el instructor
        // tiene /api/instructor/sessions para crear, y un endpoint dedicado
        // para listar guests).
        $this->getJson('/api/sessions')->assertForbidden();
        $this->postJson('/api/sessions', [
            'started_at' => now()->toIso8601String(),
        ])->assertForbidden();
    }

    public function test_instructor_can_end_their_guest_session(): void
    {
        $instructor = User::factory()->instructor()->create();
        $guest = \App\Models\GuestProfile::factory()->create([
            'created_by_user_id' => $instructor->id,
        ]);
        $session = TrainingSession::factory()->create([
            'athlete_user_id' => null,
            'guest_profile_id' => $guest->id,
            'instructor_user_id' => $instructor->id,
        ]);
        Sanctum::actingAs($instructor);

        $this->putJson("/api/sessions/{$session->id}/end", [
            'ended_at' => now()->toIso8601String(),
        ])->assertOk();

        $this->assertNotNull($session->fresh()->ended_at);
    }

    public function test_instructor_without_matching_session_gets_404_on_end(): void
    {
        $instructor = User::factory()->instructor()->create();
        Sanctum::actingAs($instructor);

        $this->putJson('/api/sessions/9999/end', [
            'ended_at' => now()->toIso8601String(),
        ])->assertNotFound();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/sessions')->assertUnauthorized();
    }

    public function test_show_returns_session_with_empty_sets_array(): void
    {
        $athlete = User::factory()->create();
        $session = TrainingSession::factory()->create([
            'athlete_user_id' => $athlete->id,
        ]);
        Sanctum::actingAs($athlete);

        $this->getJson("/api/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('id', $session->id)
            ->assertJsonPath('sets', []);
    }

    public function test_show_returns_session_with_full_nested_data(): void
    {
        $athlete = \App\Models\User::factory()->create();
        $session = TrainingSession::factory()->create([
            'athlete_user_id' => $athlete->id,
        ]);
        $set = \App\Models\TrainingSet::factory()->create([
            'session_id' => $session->id,
            'set_number' => 1,
        ]);
        $rep = \App\Models\Rep::create([
            'set_id' => $set->id,
            'rep_number' => 1,
            'duration_ms' => 2400,
            'vastus_lateralis_left_pct' => 87.2,
            'vastus_lateralis_left_peak_pct' => 112.4,
        ]);
        \App\Models\SetMetric::create([
            'set_id' => $set->id,
            'bsa_vl_pct' => 32.5,
            'bsa_vm_pct' => 28.5,
            'bsa_gmax_pct' => 25.0,
            'bsa_es_pct' => 14.0,
            'hq_ratio' => 0.45,
            'es_gmax_ratio' => 0.62,
            'intra_set_fatigue_ratio' => 0.18,
            'thresholds_version' => 1,
        ]);
        \App\Models\Recommendation::create([
            'set_id' => $set->id,
            'text' => 'ES dominante.',
            'severity' => 'MONITOR',
        ]);

        Sanctum::actingAs($athlete);

        $response = $this->getJson("/api/sessions/{$session->id}");

        $response->assertOk()
            ->assertJsonPath('id', $session->id)
            ->assertJsonCount(1, 'sets')
            ->assertJsonPath('sets.0.id', $set->id)
            ->assertJsonPath('sets.0.set_number', 1)
            ->assertJsonCount(1, 'sets.0.reps')
            ->assertJsonPath('sets.0.reps.0.rep_number', 1)
            ->assertJsonCount(1, 'sets.0.reps.0.activations')
            ->assertJsonPath('sets.0.reps.0.activations.0.muscle', 'VASTUS_LATERALIS')
            ->assertJsonPath('sets.0.reps.0.activations.0.side', 'LEFT')
            ->assertJsonPath('sets.0.reps.0.activations.0.percent_mvc', 87.2)
            ->assertJsonPath('sets.0.metrics.bsa_vl_pct', 32.5)
            ->assertJsonCount(1, 'sets.0.recommendations')
            ->assertJsonPath('sets.0.recommendations.0.severity', 'MONITOR');
    }

    public function test_show_404_when_session_belongs_to_other_athlete(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $session = TrainingSession::factory()->create(['athlete_user_id' => $b->id]);
        Sanctum::actingAs($a);

        $this->getJson("/api/sessions/{$session->id}")->assertNotFound();
    }

    public function test_show_index_response_does_not_include_sets_key(): void
    {
        $athlete = User::factory()->create();
        TrainingSession::factory()->create(['athlete_user_id' => $athlete->id]);
        Sanctum::actingAs($athlete);

        $response = $this->getJson('/api/sessions');
        $response->assertOk();
        $this->assertArrayNotHasKey('sets', $response->json('data.0'));
    }
}
