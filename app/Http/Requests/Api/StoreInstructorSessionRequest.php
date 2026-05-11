<?php

namespace App\Http\Requests\Api;

use App\Enums\DeviceSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreInstructorSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_profile_id' => ['required', 'integer', 'exists:guest_profiles,id'],
            'started_at' => ['required', 'date'],
            'exercise' => ['sometimes', 'string', 'min:1', 'max:50'],
            'device_source' => ['sometimes', new Enum(DeviceSource::class)],
        ];
    }
}
