<?php

namespace App\Http\Requests\Api;

use App\Enums\DeviceSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class PatchSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_source' => ['required', new Enum(DeviceSource::class)],
        ];
    }
}
