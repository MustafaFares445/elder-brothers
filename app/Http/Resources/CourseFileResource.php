<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasAccess = $request->user() && $this->course->activeSubscriptionFor($request->user());

        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->localizedTitle(),
            'original_name' => $this->original_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'size_label' => $this->formatBytes($this->size_bytes),
            'is_downloadable' => (bool) $this->is_downloadable,
            'is_locked' => ! $hasAccess,
            'sort_order' => $this->sort_order,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1).' GB';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
