<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiRequest;

class DownloadRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'quality' => ['nullable', 'in:hd,sd'],
            'device_id' => ['nullable', 'string', 'max:191'],
        ];
    }
}
