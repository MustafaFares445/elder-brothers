<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class ForgotPasswordRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => $this->normalizedPhone($this->input('phone'))]);
    }

    public function rules(): array
    {
        return ['phone' => ['required', 'string', 'max:20']];
    }
}
