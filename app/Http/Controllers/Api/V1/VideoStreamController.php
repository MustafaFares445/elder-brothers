<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseVideo;
use App\Services\PrivateCourseMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoStreamController extends Controller
{
    public function __invoke(
        Request $request,
        CourseVideo $video,
        PrivateCourseMediaService $media,
    ): StreamedResponse {
        abort_unless($video->status === 'ready', 404, 'VIDEO_NOT_READY');
        abort_unless(filled($video->source_path), 404, 'VIDEO_SOURCE_NOT_FOUND');

        $disk = Storage::disk($media->diskName());
        abort_unless($disk->exists($video->source_path), 404, 'VIDEO_SOURCE_NOT_FOUND');

        $absolutePath = $disk->path($video->source_path);
        abort_unless(is_file($absolutePath) && is_readable($absolutePath), 404, 'VIDEO_SOURCE_NOT_FOUND');

        clearstatcache(true, $absolutePath);
        $fileSize = filesize($absolutePath);

        abort_unless(is_int($fileSize) && $fileSize > 0, 404, 'VIDEO_SOURCE_EMPTY');

        $rangeHeader = $request->headers->get('Range');
        [$start, $end, $status] = $this->resolveRange($rangeHeader, $fileSize);
        $length = $end - $start + 1;
        $mimeType = $disk->mimeType($video->source_path) ?: 'video/mp4';
        $isHead = $request->isMethod('HEAD');

        Log::debug('Private video stream request.', [
            'video_id' => $video->id,
            'range' => $rangeHeader,
            'storage_path' => $video->source_path,
            'absolute_path' => $absolutePath,
            'file_size' => $fileSize,
            'range_start' => $start,
            'range_end' => $end,
            'response_status' => $status,
        ]);

        $headers = [
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) $length,
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.basename($video->source_path).'"',
            'Content-Encoding' => 'identity',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Accel-Buffering' => 'no',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
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
                if (fseek($handle, $start) !== 0) {
                    return;
                }

                $remaining = $length;

                while ($remaining > 0 && ! feof($handle)) {
                    $chunk = fread($handle, min(64 * 1024, $remaining));

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

    /**
     * Resolve a single byte range.
     *
     * Malformed or unsupported Range values are ignored and receive the full
     * file. A 416 response is returned only when a valid numeric start offset
     * is greater than or equal to the actual local file size.
     *
     * @return array{0:int,1:int,2:int}
     */
    private function resolveRange(?string $rangeHeader, int $fileSize): array
    {
        $fullResponse = [0, $fileSize - 1, 200];

        if ($rangeHeader === null || trim($rangeHeader) === '') {
            return $fullResponse;
        }

        if (! preg_match('/^\s*bytes\s*=\s*(.+?)\s*$/i', $rangeHeader, $unitMatch)) {
            return $fullResponse;
        }

        // ExoPlayer sends one range. If an intermediary combines ranges, use
        // the first range instead of rejecting the whole request.
        $range = trim(explode(',', $unitMatch[1], 2)[0]);

        if (! preg_match('/^(\d*)\s*-\s*(\d*)$/', $range, $matches)) {
            return $fullResponse;
        }

        $startValue = $matches[1];
        $endValue = $matches[2];

        if ($startValue === '' && $endValue === '') {
            return $fullResponse;
        }

        // Suffix range: bytes=-500
        if ($startValue === '') {
            $suffixLength = (int) $endValue;

            if ($suffixLength <= 0) {
                return $fullResponse;
            }

            $suffixLength = min($suffixLength, $fileSize);

            return [$fileSize - $suffixLength, $fileSize - 1, 206];
        }

        $start = (int) $startValue;

        if ($start >= $fileSize) {
            Log::warning('Unsatisfiable private video range.', [
                'range' => $rangeHeader,
                'requested_start' => $start,
                'file_size' => $fileSize,
            ]);

            abort(response('', 416, [
                'Accept-Ranges' => 'bytes',
                'Content-Range' => "bytes */{$fileSize}",
                'Content-Length' => '0',
            ]));
        }

        if ($endValue === '') {
            return [$start, $fileSize - 1, 206];
        }

        $end = (int) $endValue;

        if ($end < $start) {
            return $fullResponse;
        }

        return [$start, min($end, $fileSize - 1), 206];
    }
}
