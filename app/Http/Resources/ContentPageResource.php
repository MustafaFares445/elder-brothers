<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->localized('title'),
            'content' => $this->localized('content'),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
