<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rep_number' => $this->rep_number,
            'duration_ms' => $this->duration_ms,
            'activations' => $this->activationsArray(),
        ];
    }
}
