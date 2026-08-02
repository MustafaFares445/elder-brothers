<?php

namespace App\Services;

use App\Models\CourseVideo;
use App\Models\User;

final class VideoAccessService
{
    public function canWatch(User $user, CourseVideo $video): bool
    {
        if ($user->status !== 'active' || $video->status !== 'ready') {
            return false;
        }

        return $video->course
            ->subscriptions()
            ->where('user_id', $user->id)
            ->latest()
            ->first()?->isActive() ?? false;
    }
}
