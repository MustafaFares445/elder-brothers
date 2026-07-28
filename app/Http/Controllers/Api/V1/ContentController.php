<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResources;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseVideo;
use App\Models\VideoProgress;
use App\Services\PrivateCourseMediaService;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    use ApiResponse;

    public function courseContent(Request $request, Course $course)
    {
        abort_unless($course->status === 'published', 404);

        $subscription = $this->subscription($request, $course);
        $hasFullAccess = $subscription?->isActive() ?? false;
        $type = $request->string('type', 'all');

        $videos = in_array($type, ['all', 'videos'])
            ? $course->videos()
                ->where('status', 'ready')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (CourseVideo $video) => CatalogResources::video(
                    $video,
                    $request->user(),
                    ! $hasFullAccess && ! $video->is_preview,
                ))
            : collect();

        $files = in_array($type, ['all', 'files'])
            ? $course->files()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (CourseFile $file) => CatalogResources::file($file, ! $hasFullAccess))
            : collect();

        $access = $hasFullAccess
            ? 'full'
            : ($subscription?->status === 'revoked'
                ? 'revoked'
                : ($subscription ? 'expired' : 'preview_only'));

        return $this->success([
            'course' => CatalogResources::course($course, $request->user()),
            'subscription' => CatalogResources::subscription(
                $subscription,
                CatalogResources::courseProgress($course, $request->user()),
            ),
            'access_status' => $access,
            'progress_percentage' => CatalogResources::courseProgress($course, $request->user()),
            'videos' => $videos,
            'files' => $files,
        ]);
    }

    public function playbackUrl(
        Request $request,
        CourseVideo $video,
        PrivateCourseMediaService $media,
    ) {
        $request->validate([
            'quality' => ['nullable', Rule::in(['auto', 'hd', 'sd'])],
            'device_id' => ['nullable', 'string'],
            'resume' => ['nullable', 'boolean'],
        ]);

        $this->authorizeVideo($request, $video);
        abort_unless($video->status === 'ready', 409, 'VIDEO_NOT_READY');
        abort_unless(filled($video->source_path), 404, 'VIDEO_SOURCE_NOT_FOUND');

        $stream = $media->streamMedia($video);
        $progress = $video->progress()->where('user_id', $request->user()->id)->first();

        return $this->success([
            'video_id' => $video->id,
            'playback_url' => $stream['url'],
            'stream_url' => $stream['url'],
            'signature' => $stream['signature'],
            'format' => 'mp4',
            'storage' => 'private_local',
            'supports_range' => true,
            'expires_at' => $stream['expires_at'],
            'start_position_seconds' => $request->boolean('resume', true)
                ? ($progress?->last_position_seconds ?? 0)
                : 0,
            'duration_seconds' => $video->duration_seconds,
            'headers' => (object) [
                'Accept-Ranges' => 'bytes',
            ],
        ], __('api.playback_authorized'), 'PLAYBACK_AUTHORIZED');
    }

    public function stream(Request $request, CourseVideo $video, PrivateCourseMediaService $media)
    {
        abort_unless($video->status === 'ready', 404, 'VIDEO_NOT_READY');
        abort_unless(filled($video->source_path), 404, 'VIDEO_SOURCE_NOT_FOUND');

        $disk = Storage::disk($media->diskName());
        abort_unless($disk->exists($video->source_path), 404, 'VIDEO_SOURCE_NOT_FOUND');

        $size = $disk->size($video->source_path);
        [$start, $end, $status] = $this->resolveRange($request->header('Range'), $size);
        $length = $end - $start + 1;
        $absolutePath = $disk->path($video->source_path);
        $mimeType = $disk->mimeType($video->source_path) ?: 'video/mp4';
        $isHead = $request->isMethod('HEAD');

        $headers = [
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) $length,
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.basename($video->source_path).'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(function () use ($absolutePath, $start, $length, $isHead): void {
            if ($isHead) {
                return;
            }

            $handle = fopen($absolutePath, 'rb');

            if ($handle === false) {
                return;
            }

            try {
                fseek($handle, $start);
                $remaining = $length;

                while ($remaining > 0 && ! feof($handle)) {
                    $chunk = fread($handle, min(8192, $remaining));

                    if ($chunk === false || $chunk === '') {
                        break;
                    }

                    echo $chunk;
                    $remaining -= strlen($chunk);

                    if (connection_aborted()) {
                        break;
                    }
                }
            } finally {
                fclose($handle);
            }
        }, $status, $headers);
    }

    public function progress(Request $request, CourseVideo $video)
    {
        $this->authorizeVideo($request, $video);

        $data = $request->validate([
            'position_seconds' => ['required', 'integer', 'min:0'],
            'duration_seconds' => ['required', 'integer', 'min:1'],
            'watched_seconds' => ['nullable', 'integer', 'min:0'],
            'completed' => ['nullable', 'boolean'],
            'event' => ['nullable', Rule::in(['heartbeat', 'pause', 'background', 'exit', 'complete'])],
            'device_id' => ['nullable', 'string'],
        ]);

        $duration = min($video->duration_seconds, max(1, $data['duration_seconds']));
        $position = min($duration, $data['position_seconds']);
        $incoming = min($duration, $data['watched_seconds'] ?? $position);

        $progress = VideoProgress::firstOrNew([
            'user_id' => $request->user()->id,
            'course_video_id' => $video->id,
        ]);
        $progress->watched_seconds = max($progress->watched_seconds ?? 0, $incoming);
        $progress->last_position_seconds = $position;

        $percentage = (int) round(($progress->watched_seconds / $duration) * 100);
        $isComplete = ($data['completed'] ?? false)
            || $percentage >= config('elder.video_completion_percentage');

        if ($isComplete) {
            $progress->completed_at ??= now();
        }

        $progress->last_watched_at = now();
        $progress->save();

        return $this->success(
            $this->progressData($progress, $video, $request),
            __('api.progress_saved'),
            'PROGRESS_SAVED',
        );
    }

    public function complete(Request $request, CourseVideo $video)
    {
        $this->authorizeVideo($request, $video);

        $progress = VideoProgress::updateOrCreate([
            'user_id' => $request->user()->id,
            'course_video_id' => $video->id,
        ], [
            'watched_seconds' => $video->duration_seconds,
            'last_position_seconds' => $video->duration_seconds,
            'completed_at' => now(),
            'last_watched_at' => now(),
        ]);

        return $this->success(
            $this->progressData($progress, $video, $request),
            __('api.video_completed'),
            'VIDEO_COMPLETED',
        );
    }

    public function fileDownloadUrl(
        Request $request,
        CourseFile $courseFile,
        PrivateCourseMediaService $media,
    ) {
        $this->authorizeCourse($request, $courseFile->course);
        abort_unless($courseFile->is_downloadable, 403, 'DOWNLOAD_NOT_ALLOWED');

        $download = $media->temporaryMedia($courseFile->file_path ?: $courseFile->external_url);
        abort_unless($download['url'], 404, 'FILE_NOT_FOUND');

        return $this->success([
            'file_id' => $courseFile->id,
            'download_url' => $download['url'],
            'signature' => $download['signature'],
            'filename' => $courseFile->original_name,
            'mime_type' => $courseFile->mime_type,
            'extension' => $courseFile->extension,
            'size_bytes' => $courseFile->size_bytes,
            'checksum' => null,
            'storage' => $courseFile->file_path ? 'private_local' : 'external',
            'expires_at' => $download['expires_at'],
            'headers' => (object) [],
        ], __('api.download_authorized'), 'DOWNLOAD_AUTHORIZED');
    }

    public function videoDownloadUrl(
        Request $request,
        CourseVideo $video,
        PrivateCourseMediaService $media,
    ) {
        $this->authorizeVideo($request, $video);
        abort_unless($video->is_downloadable, 403, 'DOWNLOAD_NOT_ALLOWED');
        abort_unless(filled($video->source_path), 404, 'VIDEO_SOURCE_NOT_FOUND');

        $download = $media->temporaryMedia($video->source_path);

        return $this->success([
            'video_id' => $video->id,
            'download_url' => $download['url'],
            'signature' => $download['signature'],
            'filename' => 'video-'.$video->id.'.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => null,
            'quality' => $request->string('quality', 'hd'),
            'checksum' => null,
            'storage' => 'private_local',
            'expires_at' => $download['expires_at'],
            'headers' => (object) [],
        ], __('api.download_authorized'), 'DOWNLOAD_AUTHORIZED');
    }

    private function resolveRange(?string $range, int $size): array
    {
        if ($size <= 0) {
            throw new HttpResponseException(response('', 416, [
                'Content-Range' => 'bytes */0',
            ]));
        }

        if (blank($range)) {
            return [0, $size - 1, 200];
        }

        if (! preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $matches)) {
            throw $this->rangeNotSatisfiable($size);
        }

        if ($matches[1] === '' && $matches[2] === '') {
            throw $this->rangeNotSatisfiable($size);
        }

        if ($matches[1] === '') {
            $suffixLength = min((int) $matches[2], $size);
            $start = $size - $suffixLength;
            $end = $size - 1;
        } else {
            $start = (int) $matches[1];
            $end = $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1);
        }

        if ($start < 0 || $start >= $size || $end < $start) {
            throw $this->rangeNotSatisfiable($size);
        }

        return [$start, $end, 206];
    }

    private function rangeNotSatisfiable(int $size): HttpResponseException
    {
        return new HttpResponseException(response('', 416, [
            'Content-Range' => "bytes */{$size}",
            'Accept-Ranges' => 'bytes',
        ]));
    }

    private function authorizeVideo(Request $request, CourseVideo $video): void
    {
        if (! $video->is_preview) {
            $this->authorizeCourse($request, $video->course);
        }
    }

    private function authorizeCourse(Request $request, Course $course): void
    {
        abort_unless($this->subscription($request, $course)?->isActive(), 403, 'SUBSCRIPTION_REQUIRED');
    }

    private function subscription(Request $request, Course $course)
    {
        return $course->subscriptions()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();
    }

    private function progressData($progress, $video, $request): array
    {
        $percentage = min(100, (int) round(
            ($progress->watched_seconds / max(1, $video->duration_seconds)) * 100,
        ));

        return [
            'video_id' => $video->id,
            'last_position_seconds' => $progress->last_position_seconds,
            'watched_seconds' => $progress->watched_seconds,
            'progress_percentage' => $percentage,
            'is_completed' => (bool) $progress->completed_at,
            'completed_at' => $progress->completed_at?->toIso8601String(),
            'course_progress_percentage' => CatalogResources::courseProgress($video->course, $request->user()),
            'next_video_id' => $video->course->videos()
                ->where('sort_order', '>', $video->sort_order)
                ->where('status', 'ready')
                ->orderBy('sort_order')
                ->value('id'),
        ];
    }
}
