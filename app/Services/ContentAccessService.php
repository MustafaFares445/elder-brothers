<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseVideo;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ContentAccessService
{
    public function ensureAccountActive(User $user): void
    {
        if ($user->status !== 'active') {
            throw new HttpException(403, 'ACCOUNT_SUSPENDED');
        }
    }

    public function canAccessCourse(User $user, Course $course): bool
    {
        return $course->activeSubscriptionFor($user) !== null;
    }

    public function ensureVideoAccess(User $user, CourseVideo $video): void
    {
        $this->ensureAccountActive($user);

        if ($video->status !== 'ready') {
            throw new HttpException(409, 'VIDEO_NOT_READY');
        }

        if ($video->is_preview) {
            return;
        }

        if (! $this->canAccessCourse($user, $video->course)) {
            throw new HttpException(403, 'SUBSCRIPTION_REQUIRED');
        }
    }

    public function ensureFileAccess(User $user, CourseFile $file): void
    {
        $this->ensureAccountActive($user);

        if (! $this->canAccessCourse($user, $file->course)) {
            throw new HttpException(403, 'SUBSCRIPTION_REQUIRED');
        }

        if (! $file->is_downloadable) {
            throw new HttpException(403, 'DOWNLOAD_NOT_ALLOWED');
        }
    }
}
