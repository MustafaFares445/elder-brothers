<?php

namespace App\Services;

use App\Models\CourseVideo;
use App\Models\OfflineDownload;
use Illuminate\Support\Facades\URL;

final class SignedVideoUrlService
{
    public function playback(CourseVideo $video): array
    {
        $expiresAt = now()->addMinutes((int) config('elder.signed_url_ttl_minutes', 15));

        return [
            'url' => URL::temporarySignedRoute(
                'api.v1.videos.stream',
                $expiresAt,
                ['video' => $video->getRouteKey()],
            ),
            'expires_at' => $expiresAt,
        ];
    }

    public function offlineDownload(CourseVideo $video, OfflineDownload $download): array
    {
        $expiresAt = now()->addMinutes((int) config('elder.signed_url_ttl_minutes', 15));

        return [
            'url' => URL::temporarySignedRoute(
                'api.v1.video-files.download',
                $expiresAt,
                [
                    'video' => $video->getRouteKey(),
                    'download' => $download->getRouteKey(),
                ],
            ),
            'expires_at' => $expiresAt,
        ];
    }
}
