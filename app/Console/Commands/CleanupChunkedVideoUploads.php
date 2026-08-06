<?php

namespace App\Console\Commands;

use App\Models\CourseVideo;
use App\Support\ChunkedVideoUploadMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanupChunkedVideoUploads extends Command
{
    protected $signature = 'chunked-video-uploads:cleanup';

    protected $description = 'Remove expired video upload sessions and unreferenced completed files';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours((int) config('chunked_uploads.expire_after_hours', 24));
        $removed = 0;

        foreach ($disk->directories('chunked-video-uploads') as $userDirectory) {
            foreach ($disk->directories($userDirectory) as $uploadDirectory) {
                $metadataPath = "{$uploadDirectory}/metadata.json";

                if (! $disk->exists($metadataPath)) {
                    $disk->deleteDirectory($uploadDirectory);
                    $removed++;

                    continue;
                }

                try {
                    $metadata = json_decode((string) $disk->get($metadataPath), true, flags: JSON_THROW_ON_ERROR);
                    $updatedAtValue = $metadata['updated_at'] ?? $metadata['created_at'] ?? null;

                    if (! filled($updatedAtValue)) {
                        throw new \RuntimeException('Chunked upload metadata has no timestamp.');
                    }

                    $updatedAt = CarbonImmutable::parse($updatedAtValue);

                    if ($updatedAt->greaterThan($cutoff)) {
                        continue;
                    }

                    $sourcePath = $metadata['source_path'] ?? null;
                    $mediaDiskName = (string) ($metadata['disk'] ?? config('filesystems.course_media', 'local'));

                    if (filled($sourcePath)) {
                        ChunkedVideoUploadMetadata::forget($mediaDiskName, $sourcePath);
                    }

                    if (filled($sourcePath) && ! CourseVideo::query()
                        ->where('source_path', $sourcePath)
                        ->where('private_disk', $mediaDiskName)
                        ->exists()) {
                        Storage::disk($mediaDiskName)->delete($sourcePath);
                    }
                } catch (Throwable) {
                    // Corrupt metadata should not leave large temporary files forever.
                }

                $disk->deleteDirectory($uploadDirectory);
                $removed++;
            }

            if ($disk->directories($userDirectory) === [] && $disk->files($userDirectory) === []) {
                $disk->deleteDirectory($userDirectory);
            }
        }

        $this->info("Removed {$removed} expired chunked video upload session(s).");

        return self::SUCCESS;
    }
}
