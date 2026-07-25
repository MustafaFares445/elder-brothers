<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => $this->normalizedPhone($this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'device_id' => ['nullable', 'string', 'max:191'],
            'platform' => ['nullable', 'in:android,ios'],
            'fcm_token' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
