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
            'calibrations.*.mvc_value' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
