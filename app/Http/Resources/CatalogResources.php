<?php

namespace App\Http\Resources;

use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\CourseVideo;
use App\Services\PrivateCourseMediaService;

final class CatalogResources
{
    public static function year($year): array
    {
        return [
            'id' => $year->id,
            'title' => $year->translated('title'),
            'subtitle' => $year->translated('subtitle'),
            'icon' => $year->icon,
            'subjects_count' => $year->subjects_count
                ?? $year->subjects()->where('is_active', true)->count(),
            'sort_order' => $year->sort_order,
        ];
    }

    public static function subject($subject): array
    {
        $image = self::media($subject->image_url);

        return [
            'id' => $subject->id,
            'academic_year_id' => $subject->academic_year_id,
            'title' => $subject->translated('title'),
            'image_url' => $image['url'],
            'image_signature' => $image['signature'],
            'image_expires_at' => $image['expires_at'],
            'courses_count' => $subject->courses_count
                ?? $subject->courses()->where('status', 'published')->count(),
            'sort_order' => $subject->sort_order,
        ];
    }

    public static function subscription(?CourseSubscription $subscription, ?int $progress = 0): ?array
    {
        if (! $subscription) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'source' => $subscription->source,
            'status' => $subscription->isActive() ? 'active' : $subscription->status,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'revoked_at' => $subscription->revoked_at?->toIso8601String(),
            'days_remaining' => $subscription->expires_at
                ? max(0, now()->diffInDays($subscription->expires_at, false))
                : null,
            'progress_percentage' => $progress ?? 0,
        ];
    }

    public static function course(Course $course, $user = null): array
    {
        $subscription = $user
            ? $course->subscriptions()->where('user_id', $user->id)->latest()->first()
            : null;
        $progress = self::courseProgress($course, $user);
        $thumbnail = self::media($course->thumbnail_url);

        return [
            'id' => $course->id,
            'subject_id' => $course->subject_id,
            'slug' => $course->slug,
            'title' => $course->translated('title'),
            'short_description' => $course->translated('short_description')
                ?? str($course->translated('description'))->limit(140)->toString(),
            'thumbnail_url' => $thumbnail['url'],
            'thumbnail_signature' => $thumbnail['signature'],
            'thumbnail_expires_at' => $thumbnail['expires_at'],
            'is_featured' => $course->is_featured,
            'is_subscribed' => $subscription?->isActive() ?? false,
            'subscription_status' => $subscription
                ? ($subscription->isActive() ? 'active' : $subscription->status)
                : null,
            'progress_percentage' => $progress,
            'videos_count' => $course->videos_count
                ?? $course->videos()->where('status', 'ready')->count(),
            'files_count' => $course->files_count ?? $course->files()->count(),
            'total_duration_seconds' => (int) ($course->total_duration_seconds
                ?? $course->videos()->where('status', 'ready')->sum('duration_seconds')),
            'published_at' => $course->published_at?->toIso8601String(),
        ];
    }

    public static function video(CourseVideo $video, $user, bool $locked): array
    {
        $progress = $user
            ? $video->progress()->where('user_id', $user->id)->first()
            : null;
        $percentage = $video->duration_seconds
            ? min(100, (int) round((($progress?->watched_seconds ?? 0) / $video->duration_seconds) * 100))
            : 0;
        $thumbnail = self::media($video->thumbnail_url);

        return [
            'id' => $video->id,
            'course_id' => $video->course_id,
            'title' => $video->translated('title'),
            'lesson_label' => $video->translated('lesson_label'),
            'thumbnail_url' => $thumbnail['url'],
            'thumbnail_signature' => $thumbnail['signature'],
            'thumbnail_expires_at' => $thumbnail['expires_at'],
            'duration_seconds' => $video->duration_seconds,
            'watched_seconds' => $progress?->watched_seconds ?? 0,
            'last_position_seconds' => $progress?->last_position_seconds ?? 0,
            'progress_percentage' => $percentage,
            'is_completed' => (bool) $progress?->completed_at,
            'is_locked' => $locked,
            'is_preview' => $video->is_preview,
            'is_downloadable' => $video->is_downloadable,
            'sort_order' => $video->sort_order,
        ];
    }

    public static function file($file, bool $locked): array
    {
        $download = $locked
            ? self::media(null)
            : self::media($file->file_path ?: $file->external_url);

        return [
            'id' => $file->id,
            'course_id' => $file->course_id,
            'title' => $file->translated('title'),
            'original_name' => $file->original_name,
            'extension' => $file->extension,
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
            'size_label' => self::sizeLabel($file->size_bytes),
            'is_downloadable' => $file->is_downloadable,
            'is_locked' => $locked,
            'download_url' => $download['url'],
            'signature' => $download['signature'],
            'expires_at' => $download['expires_at'],
            'storage' => $file->file_path ? 'private_local' : 'external',
            'sort_order' => $file->sort_order,
        ];
    }

    /**
     * @return array{url:?string,signature:?string,expires_at:?string,is_private:bool}
     */
    public static function media(?string $path): array
    {
        return app(PrivateCourseMediaService::class)->temporaryMedia($path);
    }

    public static function courseProgress(Course $course, $user): int
    {
        if (! $user) {
            return 0;
        }

        $videos = $course->videos()->where('status', 'ready')->get(['id', 'duration_seconds']);
        $totalDuration = $videos->sum('duration_seconds');

        if ($totalDuration <= 0) {
            return 0;
        }

        $watchedDuration = $user->videoProgress()
            ->whereIn('course_video_id', $videos->pluck('id'))
            ->sum('watched_seconds');

        return min(100, (int) round(($watchedDuration / $totalDuration) * 100));
    }

    private static function sizeLabel(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $size = $bytes;

        while ($size >= 1024 && $unitIndex < 3) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 1).' '.$units[$unitIndex];
    }
}
