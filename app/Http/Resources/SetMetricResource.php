<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SetMetricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'bsa_vl_pct' => (float) $this->bsa_vl_pct,
            'bsa_vm_pct' => (float) $this->bsa_vm_pct,
            'bsa_gmax_pct' => (float) $this->bsa_gmax_pct,
            'bsa_es_pct' => (float) $this->bsa_es_pct,
            'hq_ratio' => (float) $this->hq_ratio,
            'es_gmax_ratio' => (float) $this->es_gmax_ratio,
            'intra_set_fatigue_ratio' => (float) $this->intra_set_fatigue_ratio,
            'thresholds_version' => (int) $this->thresholds_version,
        ];
    }
}
