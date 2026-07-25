<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiRequest;

class PlaybackRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'quality' => ['nullable', 'in:auto,hd,sd'],
            'device_id' => ['nullable', 'string', 'max:191'],
            'resume' => ['nullable', 'boolean'],
        ];
    }
}
