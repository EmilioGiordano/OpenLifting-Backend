<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAthleteProfileRequest;
use App\Http\Requests\Api\StoreMvcCalibrationsRequest;
use App\Http\Requests\Api\UpdateAthleteProfileRequest;
use App\Http\Resources\AthleteProfileResource;
use App\Http\Resources\MvcCalibrationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AthleteProfileController extends Controller
{
    public function show(Request $request): AthleteProfileResource
    {
        $profile = $request->user()->athleteProfile;

        if (! $profile) {
            abort(404, 'No tenés perfil de atleta creado todavía.');
        }

        return AthleteProfileResource::make($profile);
    }

    public function store(StoreAthleteProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->athleteProfile()->exists()) {
            throw ValidationException::withMessages([
                'profile' => ['Ya existe un perfil para este usuario.'],
            ]);
        }

        $profile = $user->athleteProfile()->create($request->validated());

        return AthleteProfileResource::make($profile)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAthleteProfileRequest $request): AthleteProfileResource
    {
        $profile = $request->user()->athleteProfile;

        if (! $profile) {
            abort(404, 'No tenés perfil de atleta creado todavía.');
        }

        $validated = $request->validated();

        if (! empty($validated)) {
            $profile->update($validated);
        }

        return AthleteProfileResource::make($profile->fresh());
    }

    public function storeMvc(StoreMvcCalibrationsRequest $request): AnonymousResourceCollection
    {
        $profile = $request->user()->athleteProfile;

        if (! $profile) {
            throw ValidationException::withMessages([
                'profile' => ['Tenés que crear tu perfil antes de calibrar.'],
            ]);
        }

        $calibrations = $request->validated('calibrations');

        $upserted = DB::transaction(function () use ($profile, $calibrations) {
            $now = now();
            $rows = [];

            foreach ($calibrations as $cal) {
                $rows[] = $profile->mvcCalibrations()->updateOrCreate(
                    [
                        'muscle' => $cal['muscle'],
                        'side' => $cal['side'],
                    ],
                    [
                        'mvc_value' => $cal['mvc_value'],
                        'recorded_at' => $now,
                    ],
                );
            }

            $profile->update(['calibrated_at' => $now]);

            return $rows;
        });

        return MvcCalibrationResource::collection($upserted);
    }
}
