<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseVideo;
use App\Models\OfflineDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadOfflineVideoController extends Controller
{
    public function __invoke(Request $request, CourseVideo $video): BinaryFileResponse
    {
        $download = OfflineDownload::query()->findOrFail((string) $request->query('download'));

        abort_unless($download->course_video_id === $video->id, 404);
        abort_if($download->isRevoked(), 403, 'OFFLINE_DOWNLOAD_REVOKED');
        abort_if($download->offline_expires_at->isPast(), 403, 'OFFLINE_LICENSE_EXPIRED');
        abort_unless($video->status === 'ready' && filled($video->source_path), 404);

        $disk = Storage::disk($video->private_disk ?: config('filesystems.course_media', 'local'));
        abort_unless($disk->exists($video->source_path), 404, 'VIDEO_SOURCE_NOT_FOUND');

        return response()->file($disk->path($video->source_path), [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'attachment; filename="video-'.$video->id.'.mp4"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
