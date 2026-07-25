<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiRequest;

class UpdatePreferencesRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'in:ar,en'],
            'smart_notifications' => ['sometimes', 'boolean'],
            'download_quality' => ['sometimes', 'in:auto,hd,sd'],
        ];
    }
}
