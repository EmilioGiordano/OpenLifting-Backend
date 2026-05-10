<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MvcCalibrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'muscle' => $this->muscle,
            'side' => $this->side,
            'mvc_value' => (float) $this->mvc_value,
            'recorded_at' => $this->recorded_at,
        ];
    }
}
