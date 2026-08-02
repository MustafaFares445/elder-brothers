<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseVideo;
use App\Models\OfflineDownload;
use App\Models\UserDevice;
use App\Services\OfflineDownloadService;
use App\Services\SignedVideoUrlService;
use App\Services\VideoAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfflineVideoController extends Controller
{
    use ApiResponse;

    public function registerDevice(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'app_version' => ['nullable', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'fcm_token' => ['nullable', 'string', 'max:2048'],
            'notifications_enabled' => ['nullable', 'boolean'],
        ]);

        $device = UserDevice::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'device_id' => $data['device_id'],
            ],
            [
                'platform' => $data['platform'],
                'app_version' => $data['app_version'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'fcm_token' => $data['fcm_token'] ?? '',
                'notifications_enabled' => $data['notifications_enabled'] ?? true,
                'last_seen_at' => now(),
            ],
        );

        return $this->success([
            'device_id' => $device->device_id,
            'platform' => $device->platform,
            'revoked' => $device->isRevoked(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ], 'تم تسجيل الجهاز.', 'DEVICE_REGISTERED');
    }

    public function playSession(
        Request $request,
        CourseVideo $video,
        VideoAccessService $access,
        OfflineDownloadService $downloads,
        SignedVideoUrlService $urls,
    ) {
        abort_unless($access->canWatch($request->user(), $video), 403, 'SUBSCRIPTION_REQUIRED');
        $downloads->ensureVideoMetadata($video);
        $signed = $urls->playback($video);

        return $this->success([
            'video_id' => $video->id,
            'playback_url' => $signed['url'],
            'expires_at' => $signed['expires_at']->toIso8601String(),
        ], 'تم إنشاء جلسة التشغيل.', 'PLAY_SESSION_CREATED');
    }

    public function createOfflineDownload(
        Request $request,
        CourseVideo $video,
        OfflineDownloadService $downloads,
        SignedVideoUrlService $urls,
    ) {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $device = $request->user()
            ->devices()
            ->where('device_id', $data['device_id'])
            ->first();

        abort_unless($device, 422, 'DEVICE_NOT_REGISTERED');

        $device->forceFill([
            'app_version' => $data['app_version'] ?? $device->app_version,
            'last_seen_at' => now(),
        ])->save();

        $download = $downloads->create($request->user(), $video, $device);
        $video->refresh();
        $signed = $urls->offlineDownload($video, $download);

        return $this->success([
            'download_id' => $download->id,
            'video' => [
                'id' => $video->id,
                'title' => $video->localized('title'),
                'duration_seconds' => $video->duration_seconds,
                'poster_url' => null,
            ],
            'file' => [
                'url' => $signed['url'],
                'size_bytes' => $video->size_bytes,
                'sha256' => $video->sha256,
                'mime' => 'video/mp4',
                'expires_at' => $signed['expires_at']->toIso8601String(),
            ],
            'license' => [
                'offline_expires_at' => $download->offline_expires_at->toIso8601String(),
                'refresh_after' => $download->refresh_after?->toIso8601String(),
                'can_play_offline' => true,
            ],
        ], 'تم إنشاء ترخيص التنزيل.', 'OFFLINE_DOWNLOAD_CREATED', 201);
    }

    public function complete(
        Request $request,
        OfflineDownload $download,
        OfflineDownloadService $downloads,
    ) {
        $downloads->assertOwned($request->user(), $download);
        $download->loadMissing('device');

        abort_if($download->isRevoked(), 409, 'OFFLINE_DOWNLOAD_REVOKED');
        abort_if($download->device->isRevoked(), 409, 'DEVICE_REVOKED');
        abort_if($download->offline_expires_at->isPast(), 409, 'OFFLINE_LICENSE_EXPIRED');

        $data = $request->validate([
            'encrypted_size_bytes' => ['required', 'integer', 'min:1'],
            'encrypted_sha256' => ['required', 'string', 'size:64', 'regex:/^[a-fA-F0-9]{64}$/'],
            'algorithm' => ['required', 'string', 'max:100'],
        ]);

        $download->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
            'encrypted_size_bytes' => $data['encrypted_size_bytes'],
            'encrypted_sha256' => strtolower($data['encrypted_sha256']),
            'algorithm' => $data['algorithm'],
        ])->save();

        return $this->success([
            'download_id' => $download->id,
            'status' => $download->status,
            'completed_at' => $download->completed_at->toIso8601String(),
            'offline_expires_at' => $download->offline_expires_at->toIso8601String(),
        ], 'تم تأكيد تشفير الفيديو المحلي.', 'OFFLINE_DOWNLOAD_COMPLETED');
    }

    public function refresh(
        Request $request,
        OfflineDownload $download,
        OfflineDownloadService $downloads,
    ) {
        $download = $downloads->refresh($request->user(), $download);

        return $this->success([
            'download_id' => $download->id,
            'offline_expires_at' => $download->offline_expires_at->toIso8601String(),
            'refresh_after' => $download->refresh_after?->toIso8601String(),
            'revoked' => $download->isRevoked(),
            'reason' => $download->revoke_reason,
        ], 'تم تحديث ترخيص الأوفلاين.', 'OFFLINE_LICENSE_REFRESHED');
    }

    public function destroy(
        Request $request,
        OfflineDownload $download,
        OfflineDownloadService $downloads,
    ) {
        $downloads->delete($request->user(), $download);

        return $this->success(null, 'تم حذف سجل التنزيل.', 'OFFLINE_DOWNLOAD_DELETED');
    }
}
