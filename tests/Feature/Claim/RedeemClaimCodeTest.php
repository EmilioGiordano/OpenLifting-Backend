<?php

namespace Tests\Feature\Claim;

use App\Models\AthleteProfile;
use App\Models\ClaimCode;
use App\Models\GuestProfile;
use App\Models\MvcCalibration;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RedeemClaimCodeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setupClaim(array $opts = []): array
    {
        $instructor = User::factory()->instructor()->create();
        $athlete = User::factory()->create();

        $guest = GuestProfile::factory()->create([
            'created_by_user_id' => $instructor->id,
            'first_name' => 'Diego',
            'last_name' => 'Pérez',
            'bodyweight_kg' => 92.5,
            'age_years' => 34,
            'sex' => 'MALE',
            'calibrated_at' => now(),
        ]);

        if ($opts['guest_has_calibration'] ?? true) {
            MvcCalibration::create([
                'guest_profile_id' => $guest->id,
                'vastus_lateralis_left' => 87.0,
                'vastus_lateralis_right' => 84.5,
                'gluteus_maximus_left' => 90.0,
                'recorded_at' => now(),
            ]);
        }

        $session = TrainingSession::factory()->create([
            'athlete_user_id' => null,
            'guest_profile_id' => $guest->id,
            'instructor_user_id' => $instructor->id,
        ]);

        $code = ClaimCode::factory()->create([
            'session_id' => $session->id,
            'code' => 'ABCD2345',
        ]);

        return compact('instructor', 'athlete', 'guest', 'session', 'code');
    }

    public function test_athlete_without_profile_redeems_and_inherits_everything(): void
    {
        ['athlete' => $athlete, 'guest' => $guest, 'session' => $session, 'instructor' => $instructor] = $this->setupClaim();
        Sanctum::actingAs($athlete);

        $response = $this->postJson('/api/claim', ['code' => 'ABCD2345']);

        $response->assertOk()->assertJsonPath('id', $session->id);

        // Profile copiado del guest
        $profile = $athlete->fresh()->athleteProfile;
        $this->assertNotNull($profile);
        $this->assertSame('Diego', $profile->first_name);
        $this->assertSame('92.50', (string) $profile->bodyweight_kg);
        $this->assertNotNull($profile->calibrated_at);

        // Calibraciones copiadas (nueva fila wide para el athlete profile)
        $this->assertDatabaseHas('mvc_calibrations', [
            'athlete_profile_id' => $profile->id,
            'guest_profile_id' => null,
            'vastus_lateralis_left' => 87.0,
            'gluteus_maximus_left' => 90.0,
        ]);
        // Calibraciones del guest se conservan
        $this->assertDatabaseHas('mvc_calibrations', [
            'guest_profile_id' => $guest->id,
            'athlete_profile_id' => null,
        ]);

        // Sesión transferida (XOR pivota)
        $this->assertDatabaseHas('training_sessions', [
            'id' => $session->id,
            'athlete_user_id' => $athlete->id,
            'guest_profile_id' => null,
        ]);

        // Guest queda marcado como claimed
        $this->assertDatabaseHas('guest_profiles', [
            'id' => $guest->id,
            'claimed_by_user_id' => $athlete->id,
        ]);
        $this->assertNotNull($guest->fresh()->claimed_at);

        // Pivot instructor_athlete creado
        $this->assertDatabaseHas('instructor_athlete', [
            'instructor_id' => $instructor->id,
            'athlete_id' => $athlete->id,
        ]);

        // Código marcado como usado
        $this->assertDatabaseHas('claim_codes', [
            'code' => 'ABCD2345',
            'used_by_user_id' => $athlete->id,
        ]);
    }

    public function test_athlete_with_existing_profile_only_gets_session(): void
    {
        ['athlete' => $athlete, 'session' => $session] = $this->setupClaim();

        $existingProfile = AthleteProfile::factory()->create([
            'user_id' => $athlete->id,
            'first_name' => 'OriginalName',
            'bodyweight_kg' => 70.0,
        ]);

        Sanctum::actingAs($athlete);

        $this->postJson('/api/claim', ['code' => 'ABCD2345'])->assertOk();

        // Profile NO se sobrescribió
        $this->assertSame('OriginalName', $existingProfile->fresh()->first_name);
        $this->assertSame('70.00', (string) $existingProfile->fresh()->bodyweight_kg);

        // No se creó una calibración para el athlete profile
        $this->assertDatabaseMissing('mvc_calibrations', [
            'athlete_profile_id' => $existingProfile->id,
        ]);

        // Sesión sí se transfirió
        $this->assertDatabaseHas('training_sessions', [
            'id' => $session->id,
            'athlete_user_id' => $athlete->id,
            'guest_profile_id' => null,
        ]);
    }

    public function test_invalid_code_returns_404(): void
    {
        $athlete = User::factory()->create();
        Sanctum::actingAs($athlete);

        $this->postJson('/api/claim', ['code' => 'XXXX9999'])
            ->assertStatus(404);
    }

    public function test_expired_code_returns_410(): void
    {
        ['athlete' => $athlete, 'session' => $session] = $this->setupClaim();
        ClaimCode::where('code', 'ABCD2345')->update(['expires_at' => now()->subMinute()]);
        Sanctum::actingAs($athlete);

        $this->postJson('/api/claim', ['code' => 'ABCD2345'])
            ->assertStatus(410);

        // Sanity: la sesión no se transfirió
        $this->assertDatabaseHas('training_sessions', [
            'id' => $session->id,
            'athlete_user_id' => null,
        ]);
    }

    public function test_used_code_returns_410(): void
    {
        ['athlete' => $athlete] = $this->setupClaim();
        ClaimCode::where('code', 'ABCD2345')->update([
            'used_at' => now()->subMinute(),
            'used_by_user_id' => $athlete->id,
        ]);
        Sanctum::actingAs($athlete);

        $this->postJson('/api/claim', ['code' => 'ABCD2345'])
            ->assertStatus(410);
    }

    public function test_instructor_cannot_redeem(): void
    {
        $instructor = User::factory()->instructor()->create();
        Sanctum::actingAs($instructor);

        $this->postJson('/api/claim', ['code' => 'ABCD2345'])
            ->assertForbidden();
    }

    public function test_code_normalized_to_uppercase(): void
    {
        ['athlete' => $athlete, 'session' => $session] = $this->setupClaim();
        Sanctum::actingAs($athlete);

        $this->postJson('/api/claim', ['code' => 'abcd2345'])
            ->assertOk()
            ->assertJsonPath('id', $session->id);
    }
}
