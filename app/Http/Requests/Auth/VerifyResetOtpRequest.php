<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class VerifyResetOtpRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => $this->normalizedPhone($this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'digits:6'],
        ];
    }
}
