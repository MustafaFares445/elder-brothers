<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiRequest;

class StoreDeviceRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:191'],
            'fcm_token' => ['required', 'string', 'max:2048'],
            'platform' => ['required', 'in:android,ios'],
            'app_version' => ['nullable', 'string', 'max:30'],
            'notifications_enabled' => ['nullable', 'boolean'],
        ];
    }
}
