<?php

namespace App\Http\Requests\Api;

use App\Enums\Muscle;
use App\Enums\MuscleSide;
use App\Enums\RiskLevel;
use App\Enums\SquatDepth;
use App\Enums\SquatVariant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Set fields
            'set_number'  => ['required', 'integer', 'min:1', 'max:99'],
            'load_kg'     => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'target_reps' => ['required', 'integer', 'min:1', 'max:50'],
            'variant'     => ['required', new Enum(SquatVariant::class)],
            'depth'       => ['required', new Enum(SquatDepth::class)],
            'rpe'         => ['required', 'numeric', 'min:1', 'max:10'],

            // Reps array
            'reps'                              => ['required', 'array', 'min:1', 'max:50'],
            'reps.*.rep_number'                 => ['required', 'integer', 'min:1', 'max:50'],
            'reps.*.duration_ms'                => ['sometimes', 'integer', 'min:0', 'max:600000'],

            // Activations per rep (allow empty — partial captures are valid)
            'reps.*.activations'                       => ['sometimes', 'array', 'max:10'],
            'reps.*.activations.*.muscle'              => ['required', new Enum(Muscle::class)],
            'reps.*.activations.*.side'                => ['required', new Enum(MuscleSide::class)],
            'reps.*.activations.*.percent_mvc'         => ['required', 'numeric', 'gt:0', 'max:300'],
            'reps.*.activations.*.peak_percent_mvc'    => ['required', 'numeric', 'gt:0', 'max:300'],

            // Metrics (1:1 with set, required)
            'metrics'                         => ['required', 'array'],
            'metrics.bsa_vl_pct'              => ['required', 'numeric', 'min:0', 'max:100'],
            'metrics.bsa_vm_pct'              => ['required', 'numeric', 'min:0', 'max:100'],
            'metrics.bsa_gmax_pct'            => ['required', 'numeric', 'min:0', 'max:100'],
            'metrics.bsa_es_pct'              => ['required', 'numeric', 'min:0', 'max:100'],
            'metrics.hq_ratio'                => ['required', 'numeric', 'gt:0'],
            'metrics.es_gmax_ratio'           => ['required', 'numeric', 'gt:0'],
            'metrics.intra_set_fatigue_ratio' => ['required', 'numeric', 'min:0'],
            'metrics.thresholds_version'      => ['sometimes', 'integer', 'min:1'],

            // Recommendations (can be empty — a clean set has no flags)
            'recommendations'              => ['sometimes', 'array', 'max:20'],
            'recommendations.*.text'       => ['required', 'string', 'min:1', 'max:500'],
            'recommendations.*.severity'   => ['required', new Enum(RiskLevel::class)],
            'recommendations.*.evidence'   => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $reps = $this->input('reps', []);
            if (! is_array($reps)) {
                return;
            }

            $repNumbers = [];
            foreach ($reps as $rIdx => $rep) {
                if (! is_array($rep) || ! isset($rep['rep_number'])) {
                    continue;
                }

                $rn = $rep['rep_number'];
                if (in_array($rn, $repNumbers, true)) {
                    $v->errors()->add("reps.$rIdx.rep_number", "rep_number $rn está duplicado en este set.");
                } else {
                    $repNumbers[] = $rn;
                }

                $activations = $rep['activations'] ?? [];
                if (! is_array($activations)) {
                    continue;
                }

                $slots = [];
                foreach ($activations as $aIdx => $act) {
                    if (! isset($act['muscle'], $act['side'])) {
                        continue;
                    }
                    $slot = $act['muscle'].'_'.$act['side'];
                    if (in_array($slot, $slots, true)) {
                        $v->errors()->add(
                            "reps.$rIdx.activations.$aIdx",
                            "Combinación (muscle={$act['muscle']}, side={$act['side']}) duplicada en rep {$rep['rep_number']}."
                        );
                    } else {
                        $slots[] = $slot;
                    }
                }
            }
        });
    }
}
