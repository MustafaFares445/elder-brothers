<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expiresAt = $this->expires_at;
        $daysRemaining = $expiresAt?->isFuture() ? now()->diffInDays($expiresAt) : 0;

        return [
            'id' => $this->id,
            'course' => new CourseSummaryResource($this->course),
            'source' => $this->source,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toISOString(),
            'expires_at' => $expiresAt?->toISOString(),
            'revoked_at' => $this->revoked_at?->toISOString(),
            'days_remaining' => $expiresAt ? $daysRemaining : null,
            'progress_percentage' => $this->course->progressPercentageFor($request->user()),
        ];
    }
}
