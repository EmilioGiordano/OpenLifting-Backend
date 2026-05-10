<?php

namespace Tests\Feature\Sets;

use App\Models\TrainingSession;
use App\Models\TrainingSet;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreSetTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'set_number' => 1,
            'load_kg' => 140.5,
            'target_reps' => 5,
            'variant' => 'LOW_BAR',
            'depth' => 'PARALLEL',
            'rpe' => 8.5,
            'reps' => [
                [
                    'rep_number' => 1,
                    'duration_ms' => 2400,
                    'activations' => [
                        ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT',  'percent_mvc' => 87.2, 'peak_percent_mvc' => 112.4],
                        ['muscle' => 'VASTUS_LATERALIS', 'side' => 'RIGHT', 'percent_mvc' => 84.0, 'peak_percent_mvc' => 108.1],
                        ['muscle' => 'GLUTEUS_MAXIMUS',  'side' => 'LEFT',  'percent_mvc' => 91.0, 'peak_percent_mvc' => 120.5],
                    ],
                ],
                [
                    'rep_number' => 2,
                    'duration_ms' => 2200,
                    'activations' => [
                        ['muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT',  'percent_mvc' => 88.4, 'peak_percent_mvc' => 113.0],
                    ],
                ],
            ],
            'metrics' => [
                'bsa_vl_pct' => 32.5,
                'bsa_vm_pct' => 28.5,
                'bsa_gmax_pct' => 25.0,
                'bsa_es_pct' => 14.5,
                'hq_ratio' => 0.45,
                'es_gmax_ratio' => 0.62,
                'intra_set_fatigue_ratio' => 0.18,
            ],
            'recommendations' => [
                ['text' => 'ES dominante sobre GMax — reforzá empuje de cadera.', 'severity' => 'MONITOR', 'evidence' => 'es_gmax_ratio=0.62'],
            ],
        ], $overrides);
    }

    private function makeSession(User $athlete): TrainingSession
    {
        return TrainingSession::factory()->create([
            'athlete_user_id' => $athlete->id,
            'started_at' => now()->subMinutes(30),
        ]);
    }

    public function test_athlete_creates_full_set_in_one_request(): void
    {
        $athlete = User::factory()->create();
        $session = $this->makeSession($athlete);
        Sanctum::actingAs($athlete);

        $response = $this->postJson("/api/sessions/{$session->id}/sets", $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('set_number', 1)
            ->assertJsonPath('variant', 'LOW_BAR')
            ->assertJsonPath('depth', 'PARALLEL')
            ->assertJsonCount(2, 'reps')
            ->assertJsonCount(3, 'reps.0.activations')
            ->assertJsonPath('metrics.bsa_vl_pct', 32.5)
            ->assertJsonCount(1, 'recommendations')
            ->assertJsonPath('recommendations.0.severity', 'MONITOR');

        $this->assertDatabaseCount('training_sets', 1);
        $this->assertDatabaseCount('reps', 2);
        $this->assertDatabaseCount('set_metrics', 1);
        $this->assertDatabaseCount('recommendations', 1);

        // Activations now live as 20 columns inline on reps. The valid payload
        // populates 3 slots on rep 1 and 1 slot on rep 2 = 4 non-null pct cols total.
        $this->assertDatabaseHas('reps', [
            'rep_number' => 1,
            'vastus_lateralis_left_pct' => 87.2,
            'gluteus_maximus_left_pct' => 91.0,
        ]);
        $this->assertDatabaseHas('reps', [
            'rep_number' => 2,
            'vastus_lateralis_left_pct' => 88.4,
        ]);
    }

    public function test_idempotent_on_duplicate_set_number(): void
    {
        $athlete = User::factory()->create();
        $session = $this->makeSession($athlete);
        Sanctum::actingAs($athlete);

        $first = $this->postJson("/api/sessions/{$session->id}/sets", $this->validPayload());
        $first->assertCreated();
        $firstId = $first->json('id');

        $second = $this->postJson("/api/sessions/{$session->id}/sets", $this->validPayload([
            'load_kg' => 999.99, // ignored — first write wins
        ]));

        $second->assertOk()
            ->assertJsonPath('id', $firstId);

        $this->assertDatabaseCount('training_sets', 1);
        $this->assertDatabaseCount('reps', 2);
    }

    public function test_rejects_set_against_closed_session(): void
    {
        $athlete = User::factory()->create();
        $session = TrainingSession::factory()->ended()->create([
            'athlete_user_id' => $athlete->id,
        ]);
        Sanctum::actingAs($athlete);

        $this->postJson("/api/sessions/{$session->id}/sets", $this->validPayload())
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('training_sets', 0);
    }

    public function test_404_when_session_belongs_to_other_athlete(): void
    {
        $athleteA = User::factory()->create();
        $athleteB = User::factory()->create();
        $session = $this->makeSession($athleteB);
        Sanctum::actingAs($athleteA);

        $this->postJson("/api/sessions/{$session->id}/sets", $this->validPayload())
            ->assertNotFound();
    }

    public function test_instructor_cannot_post_sets(): void
    {
        $instructor = User::factory()->instructor()->create();
        Sanctum::actingAs($instructor);

        $this->postJson('/api/sessions/1/sets', $this->validPayload())
            ->assertForbidden();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/sessions/1/sets', $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_rejects_missing_set_number(): void
    {
        $athlete = User::factory()->create();
        $session = $this->makeSession($athlete);
        Sanctum::actingAs($athlete);

        $payload = $this->validPayload();
        unset($payload['set_number']);

        $this->postJson("/api/sessions/{$session->id}/sets", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('set_number');
    }

    public function test_rejects_invalid_variant(): void
    {
        $athlete = User::factory()->create();
        $session = $this->makeSession($athlete);
        Sanctum::actingAs($athlete);

        $this->postJson("/api/sessions/{$session->id}/sets", $this->validPayload([
            'variant' => 'FAKE',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variant');
    }

    public function test_rejects_percent_mvc_above_300(): void
    {
        $athlete = User::factory()->create();
        $session = $this->makeSession($athlete);
        Sanctum::actingAs($athlete);

        $payload = $this->validPayload();
        $payload['reps'][0]['activations'][0]['percent_mvc'] = 350.0;

        $this->postJson("/api/sessions/{$session->id}/sets", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reps.0.activations.0.percent_mvc');
    }

    public function test_rejects_percent_mvc_zero_or_negative(): void
    {
        $athlete = User::factory()->create();
        $session = $this->makeSession($athlete);
        Sanctum::actingAs($athlete);

        $payload = $this->validPayload();
        $payload['reps'][0]['activations'][0]['percent_mvc'] = 0;

        $this->postJson("/api/sessions/{$session->id}/sets", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reps.0.activations.0.percent_mvc');
    }

    public function test_accepts_percent_mvc_above_100(): void
    {
        $athlete = User::factory()->create();
        $session = $this->makeSession($athlete);
        Sanctum::actingAs($athlete);

        $payload = $this->validPayload();
        $payload['reps'][0]['activations'][0]['percent_mvc'] = 145.0;
        $payload['reps'][0]['activations'][0]['peak_percent_mvc'] = 180.0;

        $this->postJson("/api/sessions/{$session->id}/sets", $payload)
            ->assertCreated();
    }

    public function test_accepts_empty_recommendations(): void
    {
        $athlete = User::factory()->create();
        $session = $this->makeSession($athlete);
        Sanctum::actingAs($athlete);

        $payload = $this->validPayload();
        $payload['recommendations'] = [];

        $this->postJson("/api/sessions/{$session->id}/sets", $payload)
            ->assertCreated()
            ->assertJsonCount(0, 'recommendations');
    }

    public function test_accepts_rep_with_no_activations(): void
    {
        $athlete = User::factory()->create();
        $session = $this->makeSession($athlete);
        Sanctum::actingAs($athlete);

        $payload = $this->validPayload();
        $payload['reps'][1]['activations'] = []; // electrode failure mid-set

        $this->postJson("/api/sessions/{$session->id}/sets", $payload)
            ->assertCreated();

        $this->assertDatabaseCount('reps', 2);

        // Rep 2 had electrode failure (empty activations[]) → all 20 cols NULL
        $this->assertDatabaseHas('reps', [
            'rep_number' => 2,
            'vastus_lateralis_left_pct' => null,
            'vastus_lateralis_right_pct' => null,
            'gluteus_maximus_left_pct' => null,
        ]);
    }

    public function test_rejects_duplicate_rep_numbers(): void
    {
        $athlete = User::factory()->create();
        $session = $this->makeSession($athlete);
        Sanctum::actingAs($athlete);

        $payload = $this->validPayload();
        $payload['reps'][1]['rep_number'] = 1; // collision

        $this->postJson("/api/sessions/{$session->id}/sets", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reps.1.rep_number');
    }

    public function test_rejects_duplicate_activation_slot_in_rep(): void
    {
        $athlete = User::factory()->create();
        $session = $this->makeSession($athlete);
        Sanctum::actingAs($athlete);

        $payload = $this->validPayload();
        $payload['reps'][0]['activations'][1] = [
            'muscle' => 'VASTUS_LATERALIS', 'side' => 'LEFT',
            'percent_mvc' => 90.0, 'peak_percent_mvc' => 110.0,
        ];

        $this->postJson("/api/sessions/{$session->id}/sets", $payload)
            ->assertUnprocessable();
    }

    public function test_rejects_invalid_severity(): void
    {
        $athlete = User::factory()->create();
        $session = $this->makeSession($athlete);
        Sanctum::actingAs($athlete);

        $this->postJson("/api/sessions/{$session->id}/sets", $this->validPayload([
            'recommendations' => [
                ['text' => 'x', 'severity' => 'CATASTROPHIC'],
            ],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recommendations.0.severity');
    }

    public function test_patch_session_updates_device_source(): void
    {
        $athlete = User::factory()->create();
        $session = TrainingSession::factory()->create([
            'athlete_user_id' => $athlete->id,
            'device_source' => 'SIMULATED',
        ]);
        Sanctum::actingAs($athlete);

        $this->patchJson("/api/sessions/{$session->id}", ['device_source' => 'REAL'])
            ->assertOk()
            ->assertJsonPath('device_source', 'REAL');
    }

    public function test_patch_session_rejects_invalid_device_source(): void
    {
        $athlete = User::factory()->create();
        $session = TrainingSession::factory()->create(['athlete_user_id' => $athlete->id]);
        Sanctum::actingAs($athlete);

        $this->patchJson("/api/sessions/{$session->id}", ['device_source' => 'FAKE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_source');
    }
}
