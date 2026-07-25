<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class LoginRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => $this->normalizedPhone($this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'device_id' => ['nullable', 'string', 'max:191'],
            'platform' => ['nullable', 'in:android,ios'],
            'fcm_token' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
