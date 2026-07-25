<?php

namespace App\Http\Requests\Subscription;

use App\Http\Requests\ApiRequest;

class QrPreviewRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:2048']];
    }
}
