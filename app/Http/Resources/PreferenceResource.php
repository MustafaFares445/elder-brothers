<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'locale' => $this->locale,
            'smart_notifications' => (bool) $this->smart_notifications,
            'download_quality' => $this->download_quality,
        ];
    }
}
