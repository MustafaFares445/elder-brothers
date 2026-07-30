<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $seconds = (int) $this->videoProgress()->sum('watched_seconds');

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'avatar_url' => $this->avatar_path ? asset('storage/'.$this->avatar_path) : null,
            'status' => $this->status,
            'is_active' => $this->status === 'active',
            'courses_count' => $this->subscriptions()->distinct('course_id')->count('course_id'),
            'watched_hours' => round($seconds / 3600, 1),
            'unread_notifications_count' => $this->unreadNotifications()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}