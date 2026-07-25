<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $progress = $user
            ? $this->progress()->where('user_id', $user->id)->first()
            : null;
        $hasAccess = $this->is_preview || ($user && $this->course->activeSubscriptionFor($user));

        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->localized('title'),
            'lesson_label' => $this->localized('lesson_label'),
            'thumbnail_url' => $this->thumbnail_url,
            'duration_seconds' => $this->duration_seconds,
            'watched_seconds' => $progress?->watched_seconds ?? 0,
            'last_position_seconds' => $progress?->last_position_seconds ?? 0,
            'progress_percentage' => $this->duration_seconds > 0
                ? min(100, (int) round((($progress?->watched_seconds ?? 0) / $this->duration_seconds) * 100))
                : 0,
            'is_completed' => $progress?->completed_at !== null,
            'is_locked' => ! $hasAccess,
            'is_preview' => (bool) $this->is_preview,
            'is_downloadable' => (bool) $this->is_downloadable,
            'sort_order' => $this->sort_order,
        ];
    }
}
