<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exercise' => $this->exercise,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'device_source' => $this->device_source,
            'created_at' => $this->created_at,
            // Only emitted by GET /api/sessions/{id} which eager-loads sets.
            // List/POST/PUT/PATCH responses omit this key entirely.
            'sets' => $this->when(
                $this->relationLoaded('sets'),
                fn () => SetResource::collection($this->sets),
            ),
        ];
    }
}
