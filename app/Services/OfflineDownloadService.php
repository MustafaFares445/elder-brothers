<?php

namespace App\Services;

use App\Models\CourseVideo;
use App\Models\OfflineDownload;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class OfflineDownloadService
{
    public function __construct(
        private readonly VideoAccessService $access,
    ) {
    }

    public function create(User $user, CourseVideo $video, UserDevice $device): OfflineDownload
    {
        if ($device->user_id !== $user->id || $device->isRevoked()) {
            throw ValidationException::withMessages([
                'device_id' => ['الجهاز غير صالح أو تم إلغاؤه.'],
            ]);
        }

        if (! $this->access->canWatch($user, $video)) {
            throw new HttpException(403, 'SUBSCRIPTION_REQUIRED');
        }

        $deviceCount = $user->devices()->whereNull('revoked_at')->count();

        if ($deviceCount > (int) config('elder.offline.max_devices_per_user', 3)) {
            throw ValidationException::withMessages([
                'device_id' => ['تم تجاوز الحد الأقصى للأجهزة المسموح بها.'],
            ]);
        }

        $activeDownloads = OfflineDownload::query()
            ->where('user_id', $user->id)
            ->where('course_video_id', $video->id)
            ->whereNotIn('status', ['deleted', 'revoked'])
            ->whereNull('revoked_at')
            ->count();

        if ($activeDownloads >= (int) config('elder.offline.max_downloads_per_video', 2)) {
            throw ValidationException::withMessages([
                'video_id' => ['تم تجاوز الحد الأقصى لتنزيل هذا الفيديو.'],
            ]);
        }

        $this->ensureVideoMetadata($video);

        return DB::transaction(function () use ($user, $video, $device): OfflineDownload {
            return OfflineDownload::create([
                'user_id' => $user->id,
                'course_video_id' => $video->id,
                'user_device_id' => $device->id,
                'status' => 'created',
                'offline_expires_at' => now()->addDays((int) config('elder.offline.license_days', 30)),
                'refresh_after' => now()->addDays((int) config('elder.offline.refresh_after_days', 20)),
            ]);
        });
    }

    public function refresh(User $user, OfflineDownload $download): OfflineDownload
    {
        $this->assertOwned($user, $download);

        if ($download->isRevoked()) {
            return $download;
        }

        $download->loadMissing(['video.course', 'device']);

        if ($download->device->isRevoked()) {
            return $this->revoke($download, 'device_revoked');
        }

        if (! $this->access->canWatch($user, $download->video)) {
            return $this->revoke($download, 'subscription_expired');
        }

        $download->forceFill([
            'status' => $download->completed_at ? 'completed' : 'created',
            'offline_expires_at' => now()->addDays((int) config('elder.offline.license_days', 30)),
            'refresh_after' => now()->addDays((int) config('elder.offline.refresh_after_days', 20)),
            'revoked_at' => null,
            'revoke_reason' => null,
        ])->save();

        return $download->fresh();
    }

    public function revoke(OfflineDownload $download, string $reason): OfflineDownload
    {
        $download->forceFill([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ])->save();

        return $download->fresh();
    }

    public function delete(User $user, OfflineDownload $download): OfflineDownload
    {
        $this->assertOwned($user, $download);

        $download->forceFill([
            'status' => 'deleted',
            'revoked_at' => now(),
            'revoke_reason' => 'user_deleted',
        ])->save();

        return $download->fresh();
    }

    public function assertOwned(User $user, OfflineDownload $download): void
    {
        if ($download->user_id !== $user->id) {
            throw new HttpException(403, 'OFFLINE_DOWNLOAD_NOT_FOUND');
        }
    }

    public function ensureVideoMetadata(CourseVideo $video): void
    {
        if (filled($video->size_bytes) && filled($video->sha256)) {
            return;
        }

        $diskName = $video->private_disk ?: (string) config('filesystems.course_media', 'local');
        $disk = Storage::disk($diskName);

        if (! filled($video->source_path) || ! $disk->exists($video->source_path)) {
            throw ValidationException::withMessages([
                'video' => ['ملف الفيديو غير موجود في التخزين الخاص.'],
            ]);
        }

        $stream = $disk->readStream($video->source_path);

        if (! is_resource($stream)) {
            throw ValidationException::withMessages([
                'video' => ['تعذر قراءة ملف الفيديو.'],
            ]);
        }

        $context = hash_init('sha256');

        while (! feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);

            if ($chunk === false) {
                break;
            }

            hash_update($context, $chunk);
        }

        fclose($stream);

        $video->forceFill([
            'private_disk' => $diskName,
            'size_bytes' => $disk->size($video->source_path),
            'sha256' => hash_final($context),
        ])->saveQuietly();
    }
}
