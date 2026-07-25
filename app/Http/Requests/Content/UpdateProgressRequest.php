<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiRequest;

class UpdateProgressRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'position_seconds' => ['required', 'integer', 'min:0'],
            'duration_seconds' => ['required', 'integer', 'min:1'],
            'watched_seconds' => ['nullable', 'integer', 'min:0'],
            'completed' => ['nullable', 'boolean'],
            'event' => ['nullable', 'in:heartbeat,pause,background,exit,complete'],
            'device_id' => ['nullable', 'string', 'max:191'],
        ];
    }
}
