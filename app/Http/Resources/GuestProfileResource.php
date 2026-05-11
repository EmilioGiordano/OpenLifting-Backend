<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'bodyweight_kg' => (float) $this->bodyweight_kg,
            'age_years' => $this->age_years,
            'sex' => $this->sex,
            'calibrated_at' => $this->calibrated_at,
            'claimed' => $this->isClaimed(),
            'claimed_at' => $this->claimed_at,
            'created_at' => $this->created_at,
        ];
    }
}
