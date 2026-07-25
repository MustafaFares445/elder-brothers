<?php

namespace App\Services;

use App\Models\CourseVideo;
use App\Models\User;
use App\Models\VideoProgress;

class VideoProgressService
{
    public function update(User $user, CourseVideo $video, array $data): array
    {
        $duration = max(1, $video->duration_seconds);
        $position = min($duration, max(0, (int) ($data['position_seconds'] ?? 0)));
        $submittedWatched = min($duration, max($position, (int) ($data['watched_seconds'] ?? $position)));

        $progress = VideoProgress::firstOrNew([
            'user_id' => $user->id,
            'course_video_id' => $video->id,
        ]);

        $progress->watched_seconds = max((int) $progress->watched_seconds, $submittedWatched);
        $progress->last_position_seconds = $position;
        $progress->last_watched_at = now();

        $percentage = (int) round(($progress->watched_seconds / $duration) * 100);
        if (($data['completed'] ?? false) || ($data['event'] ?? null) === 'complete' || $percentage >= 90) {
            $progress->completed_at ??= now();
            $progress->watched_seconds = max($progress->watched_seconds, (int) round($duration * 0.9));
            $percentage = max(90, $percentage);
        }

        $progress->save();

        $nextVideoId = $video->course->videos()
            ->where('status', 'ready')
            ->where('sort_order', '>', $video->sort_order)
            ->orderBy('sort_order')
            ->value('id');

        return [
            'progress' => $progress,
            'progress_percentage' => min(100, $percentage),
            'course_progress_percentage' => $video->course->progressPercentageFor($user),
            'next_video_id' => $nextVideoId,
        ];
    }
}
