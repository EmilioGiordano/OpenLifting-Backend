<?php

namespace App\Http\Requests\Api;

use App\Enums\Sex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAthleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'min:1', 'max:100'],
            'last_name' => ['sometimes', 'string', 'min:1', 'max:100'],
            'bodyweight_kg' => ['sometimes', 'numeric', 'between:30,300'],
            'age_years' => ['sometimes', 'integer', 'between:14,100'],
            'sex' => ['sometimes', new Enum(Sex::class)],
        ];
    }
}
