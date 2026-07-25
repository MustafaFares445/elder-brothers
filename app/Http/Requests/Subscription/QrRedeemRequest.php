<?php

namespace App\Http\Requests\Subscription;

use App\Http\Requests\ApiRequest;

class QrRedeemRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:2048'],
            'device_id' => ['nullable', 'string', 'max:191'],
            'confirm' => ['required', 'accepted'],
        ];
    }
}
