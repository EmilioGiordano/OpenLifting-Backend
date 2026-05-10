<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EndSessionRequest;
use App\Http\Requests\Api\PatchSessionRequest;
use App\Http\Requests\Api\StoreSessionRequest;
use App\Http\Resources\SessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SessionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sessions = $request->user()
            ->trainingSessions()
            ->orderByDesc('started_at')
            ->paginate(15);

        return SessionResource::collection($sessions);
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        $session = $request->user()
            ->trainingSessions()
            ->create($request->validated());

        return SessionResource::make($session)
            ->response()
            ->setStatusCode(201);
    }

    public function end(EndSessionRequest $request, int $sessionId): SessionResource
    {
        $session = $request->user()
            ->trainingSessions()
            ->findOrFail($sessionId);

        $session->update($request->validated());

        return SessionResource::make($session->fresh());
    }

    public function patch(PatchSessionRequest $request, int $sessionId): SessionResource
    {
        $session = $request->user()
            ->trainingSessions()
            ->findOrFail($sessionId);

        $session->update($request->validated());

        return SessionResource::make($session->fresh());
    }
}
