<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicYearResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->localized('title'),
            'subtitle' => $this->localized('subtitle'),
            'icon' => $this->icon,
            'subjects_count' => $this->subjects_count ?? $this->subjects()->where('is_active', true)->count(),
            'sort_order' => $this->sort_order,
        ];
    }
}
