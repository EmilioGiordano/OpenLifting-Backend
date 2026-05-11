<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RedeemClaimCodeRequest;
use App\Http\Resources\SessionResource;
use App\Models\AthleteProfile;
use App\Models\ClaimCode;
use App\Models\MvcCalibration;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClaimController extends Controller
{
    /**
     * Canjea un código de invitación. Atómico: la sesión del guest pasa a
     * pertenecer al athlete, el guest queda marcado como claimed, y el
     * código se marca como usado. Si el athlete no tiene profile, se copia
     * el del guest (incluyendo calibraciones).
     */
    public function redeem(RedeemClaimCodeRequest $request): JsonResponse
    {
        $athlete = $request->user();
        $code = ClaimCode::where('code', $request->validated('code'))->first();

        if (! $code) {
            return response()->json([
                'message' => 'Código inválido.',
            ], 404);
        }

        if ($code->isUsed()) {
            return response()->json([
                'message' => 'Este código ya fue canjeado.',
            ], 410);
        }

        if ($code->isExpired()) {
            return response()->json([
                'message' => 'Este código expiró. Pedile uno nuevo a tu instructor.',
            ], 410);
        }

        $session = DB::transaction(function () use ($athlete, $code) {
            $session = $code->session()->lockForUpdate()->firstOrFail();
            $guest = $session->guestProfile()->lockForUpdate()->firstOrFail();

            // 1. Si el athlete no tiene profile, copiar del guest
            $profile = $athlete->athleteProfile;

            if ($profile === null) {
                $profile = AthleteProfile::create([
                    'user_id' => $athlete->id,
                    'first_name' => $guest->first_name,
                    'last_name' => $guest->last_name,
                    'bodyweight_kg' => $guest->bodyweight_kg,
                    'age_years' => $guest->age_years,
                    'sex' => $guest->sex->value,
                    'calibrated_at' => $guest->calibrated_at,
                ]);

                // Copiar calibraciones del guest al nuevo profile
                $guestMvc = $guest->mvcCalibration;
                if ($guestMvc !== null) {
                    $cols = [];
                    foreach (MvcCalibration::CALIBRATION_SLOTS as $slot) {
                        $cols[$slot['col']] = $guestMvc->{$slot['col']};
                    }

                    MvcCalibration::create(array_merge($cols, [
                        'athlete_profile_id' => $profile->id,
                        'recorded_at' => $guestMvc->recorded_at,
                    ]));
                }
            }

            // 2. Transferir la sesión (pivote del XOR)
            $session->update([
                'athlete_user_id' => $athlete->id,
                'guest_profile_id' => null,
            ]);

            // 3. Marcar guest como claimed
            $guest->update([
                'claimed_by_user_id' => $athlete->id,
                'claimed_at' => now(),
            ]);

            // 4. Vincular athlete con instructor (si todavía no estaba)
            if ($session->instructor_user_id !== null) {
                DB::table('instructor_athlete')
                    ->insertOrIgnore([
                        'instructor_id' => $session->instructor_user_id,
                        'athlete_id' => $athlete->id,
                        'linked_at' => now(),
                    ]);
            }

            // 5. Marcar código como usado
            $code->update([
                'used_at' => now(),
                'used_by_user_id' => $athlete->id,
            ]);

            return $session->fresh();
        });

        return SessionResource::make($session)
            ->response()
            ->setStatusCode(200);
    }
}
