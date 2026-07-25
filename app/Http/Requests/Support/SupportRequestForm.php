<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\ApiRequest;

class SupportRequestForm extends ApiRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'category' => ['nullable', 'in:technical,subscription,content,account,other'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:10240'],
            'device_info' => ['nullable', 'array'],
        ];
    }
}
