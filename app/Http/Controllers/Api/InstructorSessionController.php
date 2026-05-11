<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInstructorSessionRequest;
use App\Http\Resources\ClaimCodeResource;
use App\Http\Resources\SessionResource;
use App\Models\ClaimCode;
use App\Models\TrainingSession;
use App\Support\ClaimCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstructorSessionController extends Controller
{
    public function store(StoreInstructorSessionRequest $request): JsonResponse
    {
        $instructor = $request->user();
        $data = $request->validated();

        $guest = $instructor->createdGuests()->find($data['guest_profile_id']);

        if (! $guest) {
            // El guest existe (validation `exists`) pero no fue creado por este
            // instructor. 404 en lugar de 403 para no leakear existencia.
            abort(404);
        }

        if ($guest->isClaimed()) {
            throw ValidationException::withMessages([
                'guest_profile_id' => ['Este guest ya fue reclamado, no se le pueden crear sesiones nuevas.'],
            ]);
        }

        $session = TrainingSession::create([
            'athlete_user_id' => null,
            'guest_profile_id' => $guest->id,
            'instructor_user_id' => $instructor->id,
            'exercise' => $data['exercise'] ?? 'back_squat',
            'started_at' => $data['started_at'],
            'device_source' => $data['device_source'] ?? 'SIMULATED',
        ]);

        return SessionResource::make($session)
            ->response()
            ->setStatusCode(201);
    }

    public function generateClaimCode(int $sessionId): JsonResponse
    {
        $instructor = request()->user();

        $session = TrainingSession::where('id', $sessionId)
            ->where('instructor_user_id', $instructor->id)
            ->whereNotNull('guest_profile_id')
            ->first();

        if (! $session) {
            abort(404);
        }

        // Invalidar códigos activos previos (regla "1 código activo por sesión")
        $code = DB::transaction(function () use ($session, $instructor) {
            $session->claimCodes()
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            return ClaimCode::create([
                'session_id' => $session->id,
                'code' => ClaimCodeGenerator::generate(),
                'expires_at' => now()->addMinutes(5),
                'created_at' => now(),
            ]);
        });

        return ClaimCodeResource::make($code)
            ->response()
            ->setStatusCode(201);
    }
}
