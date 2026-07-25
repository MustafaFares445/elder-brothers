<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => $this->normalizedPhone($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191', Rule::unique('users')->ignore($this->user()?->id)],
            'phone' => ['sometimes', 'string', 'max:20', Rule::unique('users')->ignore($this->user()?->id)],
        ];
    }
}
