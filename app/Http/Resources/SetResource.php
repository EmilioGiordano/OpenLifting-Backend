<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'set_number' => $this->set_number,
            'load_kg' => (float) $this->load_kg,
            'target_reps' => $this->target_reps,
            'variant' => $this->variant,
            'depth' => $this->depth,
            'rpe' => (float) $this->rpe,
            'created_at' => $this->created_at,
            'reps' => RepResource::collection($this->whenLoaded('reps')),
            'metrics' => new SetMetricResource($this->whenLoaded('metrics')),
            'recommendations' => RecommendationResource::collection($this->whenLoaded('recommendations')),
        ];
    }
}
