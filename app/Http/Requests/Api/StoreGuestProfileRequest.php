<?php

namespace App\Http\Requests\Api;

use App\Enums\Sex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreGuestProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:1', 'max:100'],
            'last_name' => ['required', 'string', 'min:1', 'max:100'],
            'bodyweight_kg' => ['required', 'numeric', 'between:30,300'],
            'age_years' => ['required', 'integer', 'between:14,100'],
            'sex' => ['required', new Enum(Sex::class)],
        ];
    }
}
