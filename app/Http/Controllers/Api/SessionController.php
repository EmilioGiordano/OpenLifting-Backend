<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EndSessionRequest;
use App\Http\Requests\Api\PatchSessionRequest;
use App\Http\Requests\Api\StoreSessionRequest;
use App\Http\Resources\SessionResource;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SessionController extends Controller
{
    /**
     * Sessions accesibles para el usuario (lectura + escritura sobre la sesión
     * en sí, no sobre los datos): athlete dueño, O instructor que la creó
     * para un guest todavía no reclamado. Cualquier otro caso → 404.
     */
    private function accessibleSessionsQuery(User $user): Builder
    {
        return TrainingSession::where(function ($q) use ($user) {
            $q->where('athlete_user_id', $user->id)
                ->orWhere(function ($q2) use ($user) {
                    $q2->where('instructor_user_id', $user->id)
                        ->whereNotNull('guest_profile_id');
                });
        });
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        // Index sigue siendo athlete-only (historial propio). El instructor
        // tiene un endpoint distinto para listar sus guests/sesiones.
        $sessions = $request->user()
            ->trainingSessions()
            ->orderByDesc('started_at')
            ->paginate(15);

        return SessionResource::collection($sessions);
    }

    public function show(Request $request, int $sessionId): SessionResource
    {
        $session = $this->accessibleSessionsQuery($request->user())
            ->where('id', $sessionId)
            ->with(['sets.reps', 'sets.metrics', 'sets.recommendations'])
            ->firstOrFail();

        return SessionResource::make($session);
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        // Solo athletes crean sesiones acá. El instructor usa
        // POST /api/instructor/sessions para sesiones guest.
        $session = $request->user()
            ->trainingSessions()
            ->create($request->validated());

        return SessionResource::make($session)
            ->response()
            ->setStatusCode(201);
    }

    public function end(EndSessionRequest $request, int $sessionId): SessionResource
    {
        $session = $this->accessibleSessionsQuery($request->user())
            ->where('id', $sessionId)
            ->firstOrFail();

        $session->update($request->validated());

        return SessionResource::make($session->fresh());
    }

    public function patch(PatchSessionRequest $request, int $sessionId): SessionResource
    {
        $session = $this->accessibleSessionsQuery($request->user())
            ->where('id', $sessionId)
            ->firstOrFail();

        $session->update($request->validated());

        return SessionResource::make($session->fresh());
    }
}
