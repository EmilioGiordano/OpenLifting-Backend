<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RedeemClaimCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 8 chars exactos (ver ClaimCodeGenerator); alfabeto restringido
            // (sin 0/O/1/I/L), no validamos el alfabeto acá porque el lookup
            // por code es UNIQUE — si no existe se devuelve 404.
            'code' => ['required', 'string', 'size:8'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->code)) {
            $this->merge(['code' => strtoupper(trim($this->code))]);
        }
    }
}
