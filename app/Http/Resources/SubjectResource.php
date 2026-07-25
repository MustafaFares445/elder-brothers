<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'title' => $this->localizedTitle(),
            'image_url' => $this->image_url,
            'courses_count' => $this->courses_count ?? $this->courses()->published()->count(),
            'sort_order' => $this->sort_order,
        ];
    }
}
