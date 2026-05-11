<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGuestProfileRequest;
use App\Http\Requests\Api\StoreMvcCalibrationsRequest;
use App\Http\Resources\GuestProfileResource;
use App\Models\GuestProfile;
use App\Models\MvcCalibration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class InstructorGuestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $guests = $request->user()
            ->createdGuests()
            ->orderByDesc('created_at')
            ->paginate(20);

        return GuestProfileResource::collection($guests);
    }

    public function store(StoreGuestProfileRequest $request): JsonResponse
    {
        $guest = $request->user()
            ->createdGuests()
            ->create($request->validated());

        return GuestProfileResource::make($guest)
            ->response()
            ->setStatusCode(201);
    }

    public function storeMvc(StoreMvcCalibrationsRequest $request, int $guestId): JsonResponse
    {
        $guest = $request->user()
            ->createdGuests()
            ->findOrFail($guestId);

        $calibrations = $request->validated('calibrations');

        $mvc = DB::transaction(function () use ($guest, $calibrations) {
            $now = now();
            $cols = MvcCalibration::mapCalibrationsToColumns($calibrations);

            $row = MvcCalibration::updateOrCreate(
                ['guest_profile_id' => $guest->id],
                array_merge($cols, ['recorded_at' => $now]),
            );

            $guest->update(['calibrated_at' => $now]);

            return $row->fresh();
        });

        return response()->json($mvc->calibrationsArray());
    }
}
