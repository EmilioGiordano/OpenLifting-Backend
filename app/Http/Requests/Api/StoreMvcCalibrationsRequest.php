<?php

namespace App\Http\Requests\Api;

use App\Enums\Muscle;
use App\Enums\MuscleSide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreMvcCalibrationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'calibrations' => ['required', 'array', 'min:1'],
            'calibrations.*.muscle' => ['required', new Enum(Muscle::class)],
            'calibrations.*.side' => ['required', new Enum(MuscleSide::class)],
            // mvc_value is %MVC in (0, 100]; see docs/adr/0001-emg-data-scale.md.
            'calibrations.*.mvc_value' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }
}
