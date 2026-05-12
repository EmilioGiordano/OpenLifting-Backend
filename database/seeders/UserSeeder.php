<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AthleteProfile;
use App\Models\MvcCalibration;
use App\Models\Recommendation;
use App\Models\Rep;
use App\Models\Role;
use App\Models\SetMetric;
use App\Models\TrainingSession;
use App\Models\TrainingSet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $instructorRoleId = Role::where('name', UserRole::INSTRUCTOR->value)->value('id');
        $athleteRoleId = Role::where('name', UserRole::ATHLETE->value)->value('id');

        // 1. Instructor — Breeze Fitness
        User::firstOrCreate(
            ['email' => 'usuario@trainer1.com'],
            [
                'name' => 'Breeze Fitness',
                'password' => Hash::make('Password'),
                'role_id' => $instructorRoleId,
            ],
        );

        // 2. Athlete sin perfil ni calibración — John Carpener
        User::firstOrCreate(
            ['email' => 'usuario@atleta1.com'],
            [
                'name' => 'John Carpener',
                'password' => Hash::make('Password'),
                'role_id' => $athleteRoleId,
            ],
        );

        // 3. Athlete con perfil + calibración cargados — Marco Cuarto
        $marco = User::firstOrCreate(
            ['email' => 'usuario@atleta2.com'],
            [
                'name' => 'Marco Cuarto',
                'password' => Hash::make('Password'),
                'role_id' => $athleteRoleId,
            ],
        );

        $profile = AthleteProfile::firstOrCreate(
            ['user_id' => $marco->id],
            [
                'first_name' => 'Marco',
                'last_name' => 'Cuarto',
                'bodyweight_kg' => 80.00,
                'age_years' => 26,
                'sex' => 'MALE',
                'calibrated_at' => now(),
            ],
        );

        MvcCalibration::updateOrCreate(
            ['athlete_profile_id' => $profile->id],
            [
                'vastus_lateralis_left' => 87.5,
                'vastus_lateralis_right' => 85.2,
                'vastus_medialis_left' => 82.4,
                'vastus_medialis_right' => 80.8,
                'gluteus_maximus_left' => 91.3,
                'gluteus_maximus_right' => 89.7,
                'erector_spinae_left' => 78.6,
                'erector_spinae_right' => 79.1,
                'biceps_femoris_left' => 76.0,
                'biceps_femoris_right' => 74.5,
                'recorded_at' => now(),
            ],
        );

        // 4. Athlete veterano con 1 mes de historial (15 sesiones)
        $this->seedVeteranAthlete($athleteRoleId);
    }

    private function seedVeteranAthlete(int $athleteRoleId): void
    {
        $user = User::firstOrCreate(
            ['email' => 'usuario@atleta3.com'],
            [
                'name' => 'Tomás Veterano',
                'password' => Hash::make('Password'),
                'role_id' => $athleteRoleId,
            ],
        );

        $profile = AthleteProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => 'Tomás',
                'last_name' => 'Veterano',
                'bodyweight_kg' => 85.50,
                'age_years' => 28,
                'sex' => 'MALE',
                'calibrated_at' => now()->subDays(30),
            ],
        );

        MvcCalibration::updateOrCreate(
            ['athlete_profile_id' => $profile->id],
            [
                'vastus_lateralis_left' => 89.2,  'vastus_lateralis_right' => 87.8,
                'vastus_medialis_left' => 84.1,   'vastus_medialis_right' => 83.5,
                'gluteus_maximus_left' => 92.4,   'gluteus_maximus_right' => 90.8,
                'erector_spinae_left' => 81.0,    'erector_spinae_right' => 80.3,
                'biceps_femoris_left' => 77.6,    'biceps_femoris_right' => 76.2,
                'recorded_at' => now()->subDays(30),
            ],
        );

        // 15 sesiones espaciadas ~2 días, progresión de carga 100 → 145 kg.
        for ($i = 0; $i < 15; $i++) {
            $daysAgo = 30 - ($i * 2);
            $loadStart = 100 + ($i * 3);   // 100, 103, 106, ... 142
            $this->createSession($user->id, $daysAgo, $loadStart, $i + 1);
        }
    }

    private function createSession(int $userId, int $daysAgo, int $loadStart, int $sessionIdx): void
    {
        $startedAt = now()->subDays($daysAgo)->setTime(18, 0);
        $endedAt = (clone $startedAt)->addMinutes(55);

        $session = TrainingSession::create([
            'athlete_user_id' => $userId,
            'exercise' => 'back_squat',
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'device_source' => $sessionIdx % 3 === 0 ? 'REAL' : 'SIMULATED',
        ]);

        // 4 sets por sesión: 2 warmups + 2 top sets.
        $sets = [
            ['set_number' => 1, 'load_kg' => $loadStart * 0.6,  'target_reps' => 8, 'rpe' => 6.0, 'depth' => 'PARALLEL'],
            ['set_number' => 2, 'load_kg' => $loadStart * 0.8,  'target_reps' => 5, 'rpe' => 7.0, 'depth' => 'PARALLEL'],
            ['set_number' => 3, 'load_kg' => $loadStart,        'target_reps' => 5, 'rpe' => 8.5, 'depth' => 'BELOW_PARALLEL'],
            ['set_number' => 4, 'load_kg' => $loadStart * 1.05, 'target_reps' => 3, 'rpe' => 9.5, 'depth' => 'BELOW_PARALLEL'],
        ];

        foreach ($sets as $setData) {
            $set = TrainingSet::create([
                'session_id' => $session->id,
                'set_number' => $setData['set_number'],
                'load_kg' => round($setData['load_kg'], 2),
                'target_reps' => $setData['target_reps'],
                'variant' => 'LOW_BAR',
                'depth' => $setData['depth'],
                'rpe' => $setData['rpe'],
            ]);

            // Reps con activations. Intensidad escala con RPE (más fatiga = más activación).
            $intensity = $setData['rpe'] / 10;  // 0.6 a 0.95
            for ($r = 1; $r <= $setData['target_reps']; $r++) {
                $fatigueFactor = 1 + ($r - 1) * 0.02; // pequeño drift por fatiga intra-set
                Rep::create([
                    'set_id' => $set->id,
                    'rep_number' => $r,
                    'duration_ms' => 2400 + ($r * 100),
                    'vastus_lateralis_left_pct'  => round(75 * $intensity * $fatigueFactor, 1),
                    'vastus_lateralis_left_peak_pct'  => round(110 * $intensity * $fatigueFactor, 1),
                    'vastus_lateralis_right_pct' => round(73 * $intensity * $fatigueFactor, 1),
                    'vastus_lateralis_right_peak_pct' => round(108 * $intensity * $fatigueFactor, 1),
                    'vastus_medialis_left_pct'   => round(70 * $intensity * $fatigueFactor, 1),
                    'vastus_medialis_left_peak_pct'   => round(102 * $intensity * $fatigueFactor, 1),
                    'vastus_medialis_right_pct'  => round(72 * $intensity * $fatigueFactor, 1),
                    'vastus_medialis_right_peak_pct'  => round(104 * $intensity * $fatigueFactor, 1),
                    'gluteus_maximus_left_pct'   => round(80 * $intensity * $fatigueFactor, 1),
                    'gluteus_maximus_left_peak_pct'   => round(120 * $intensity * $fatigueFactor, 1),
                    'gluteus_maximus_right_pct'  => round(78 * $intensity * $fatigueFactor, 1),
                    'gluteus_maximus_right_peak_pct'  => round(118 * $intensity * $fatigueFactor, 1),
                    'erector_spinae_left_pct'    => round(65 * $intensity * $fatigueFactor, 1),
                    'erector_spinae_left_peak_pct'    => round(95 * $intensity * $fatigueFactor, 1),
                    'erector_spinae_right_pct'   => round(67 * $intensity * $fatigueFactor, 1),
                    'erector_spinae_right_peak_pct'   => round(97 * $intensity * $fatigueFactor, 1),
                    'biceps_femoris_left_pct'    => round(55 * $intensity * $fatigueFactor, 1),
                    'biceps_femoris_left_peak_pct'    => round(82 * $intensity * $fatigueFactor, 1),
                    'biceps_femoris_right_pct'   => round(53 * $intensity * $fatigueFactor, 1),
                    'biceps_femoris_right_peak_pct'   => round(80 * $intensity * $fatigueFactor, 1),
                ]);
            }

            SetMetric::create([
                'set_id' => $set->id,
                'bsa_vl_pct' => 28.0 + ($setData['rpe'] - 6),
                'bsa_vm_pct' => 26.0 + ($setData['rpe'] - 6),
                'bsa_gmax_pct' => 30.0 - ($setData['rpe'] - 6) * 0.5,
                'bsa_es_pct' => 16.0 - ($setData['rpe'] - 6) * 0.3,
                'hq_ratio' => 0.5 + ($setData['rpe'] - 6) * 0.02,
                'es_gmax_ratio' => 0.6 + ($setData['rpe'] - 6) * 0.03,
                'intra_set_fatigue_ratio' => 0.10 + ($setData['rpe'] - 6) * 0.04,
                'thresholds_version' => 1,
            ]);

            // Recomendaciones en sets pesados (RPE >= 8.5)
            if ($setData['rpe'] >= 8.5) {
                Recommendation::create([
                    'set_id' => $set->id,
                    'text' => 'Fatiga intra-set elevada — descansá al menos 4 min antes del próximo set.',
                    'severity' => $setData['rpe'] >= 9.5 ? 'RISK' : 'MONITOR',
                    'evidence' => 'rpe='.$setData['rpe'],
                ]);
            }
        }
    }
}
