<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class ChunkedVideoUploadMetadata
{
    public static function remember(string $disk, string $path, int $sizeBytes, string $sha256): void
    {
        Cache::put(
            self::key($disk, $path),
            [
                'size_bytes' => $sizeBytes,
                'sha256' => $sha256,
            ],
            now()->addHours((int) config('chunked_uploads.expire_after_hours', 24)),
        );
    }

    public static function pull(string $disk, string $path): ?array
    {
        $metadata = Cache::pull(self::key($disk, $path));

        return is_array($metadata) ? $metadata : null;
    }

    public static function forget(string $disk, string $path): void
    {
        Cache::forget(self::key($disk, $path));
    }

    private static function key(string $disk, string $path): string
    {
        return 'chunked-video-upload:completed:'.hash('sha256', "{$disk}|{$path}");
    }
}
