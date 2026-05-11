<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSetRequest;
use App\Http\Resources\SetResource;
use App\Models\Rep;
use App\Models\TrainingSession;
use App\Models\TrainingSet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetController extends Controller
{
    public function store(StoreSetRequest $request, int $sessionId): JsonResponse
    {
        $user = $request->user();

        // Autorización dual: athlete dueño de la sesión, O instructor que la
        // creó para un guest todavía no reclamado. Cualquier otro caso → 404.
        $session = TrainingSession::where('id', $sessionId)
            ->where(function ($q) use ($user) {
                $q->where('athlete_user_id', $user->id)
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('instructor_user_id', $user->id)
                            ->whereNotNull('guest_profile_id');
                    });
            })
            ->firstOrFail();

        if ($session->ended_at !== null) {
            return response()->json([
                'message' => 'La sesión ya está cerrada. Creá una nueva sesión para registrar más sets.',
            ], 409);
        }

        $data = $request->validated();

        $existing = TrainingSet::where('session_id', $session->id)
            ->where('set_number', $data['set_number'])
            ->first();

        if ($existing !== null) {
            $existing->load(['reps', 'metrics', 'recommendations']);

            return SetResource::make($existing)
                ->response()
                ->setStatusCode(200);
        }

        $set = DB::transaction(function () use ($session, $data) {
            $set = $session->sets()->create([
                'set_number' => $data['set_number'],
                'load_kg' => $data['load_kg'],
                'target_reps' => $data['target_reps'],
                'variant' => $data['variant'],
                'depth' => $data['depth'],
                'rpe' => $data['rpe'],
            ]);

            foreach ($data['reps'] as $repData) {
                $activationCols = Rep::mapActivationsToColumns($repData['activations'] ?? []);

                $set->reps()->create(array_merge(
                    [
                        'rep_number' => $repData['rep_number'],
                        'duration_ms' => $repData['duration_ms'] ?? 0,
                    ],
                    $activationCols,
                ));
            }

            $set->metrics()->create([
                'bsa_vl_pct' => $data['metrics']['bsa_vl_pct'],
                'bsa_vm_pct' => $data['metrics']['bsa_vm_pct'],
                'bsa_gmax_pct' => $data['metrics']['bsa_gmax_pct'],
                'bsa_es_pct' => $data['metrics']['bsa_es_pct'],
                'hq_ratio' => $data['metrics']['hq_ratio'],
                'es_gmax_ratio' => $data['metrics']['es_gmax_ratio'],
                'intra_set_fatigue_ratio' => $data['metrics']['intra_set_fatigue_ratio'],
                'thresholds_version' => $data['metrics']['thresholds_version'] ?? 1,
            ]);

            foreach ($data['recommendations'] ?? [] as $rec) {
                $set->recommendations()->create([
                    'text' => $rec['text'],
                    'severity' => $rec['severity'],
                    'evidence' => $rec['evidence'] ?? null,
                ]);
            }

            return $set;
        });

        $set->load(['reps', 'metrics', 'recommendations']);

        return SetResource::make($set)
            ->response()
            ->setStatusCode(201);
    }
}
