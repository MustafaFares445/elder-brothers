<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $subscription = $this->activeSubscriptionFor($user);

        return [
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'slug' => $this->slug,
            'title' => $this->localized('title'),
            'short_description' => $this->localized('short_description'),
            'thumbnail_url' => $this->thumbnail_url,
            'is_featured' => (bool) $this->is_featured,
            'is_subscribed' => $subscription !== null,
            'subscription_status' => $subscription?->status,
            'progress_percentage' => $this->progressPercentageFor($user),
            'videos_count' => $this->videos_count ?? $this->videos()->where('status', 'ready')->count(),
            'files_count' => $this->files_count ?? $this->files()->count(),
            'total_duration_seconds' => (int) ($this->total_duration_seconds ?? $this->videos()->where('status', 'ready')->sum('duration_seconds')),
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
