<?php

namespace App\Http\Requests\Api;

use App\Enums\DeviceSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exercise' => ['sometimes', 'string', 'min:1', 'max:50'],
            'started_at' => ['required', 'date'],
            'device_source' => ['sometimes', new Enum(DeviceSource::class)],
        ];
    }
}
