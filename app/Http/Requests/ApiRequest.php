<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function normalizedPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        return preg_replace('/[^\d+]/', '', trim($phone));
    }
}
