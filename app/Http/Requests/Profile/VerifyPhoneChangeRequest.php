<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiRequest;

class VerifyPhoneChangeRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['otp' => ['required', 'digits:6']];
    }
}
