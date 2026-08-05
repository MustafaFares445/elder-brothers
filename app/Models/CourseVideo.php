<?php

namespace App\Models;

use App\Support\ChunkedVideoUploadMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CourseVideo extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'lesson_label',
        'thumbnail_url',
        'source_path',
        'hls_manifest_path',
        'private_disk',
        'duration_seconds',
        'size_bytes',
        'sha256',
        'sort_order',
        'is_preview',
        'is_downloadable',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'lesson_label' => 'array',
            'size_bytes' => 'integer',
            'is_preview' => 'boolean',
            'is_downloadable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CourseVideo $video): void {
            if (! $video->course_id) {
                return;
            }

            $requestedOrder = (int) ($video->sort_order ?? 0);
            $orderAlreadyUsed = $requestedOrder > 0
                && static::query()
                    ->where('course_id', $video->course_id)
                    ->where('sort_order', $requestedOrder)
                    ->exists();

            if ($requestedOrder <= 0 || $orderAlreadyUsed) {
                $video->sort_order = static::query()
                    ->where('course_id', $video->course_id)
                    ->max('sort_order') + 1;
            }
        });

        static::saving(function (CourseVideo $video): void {
            $video->is_preview = false;
            $video->is_downloadable = true;
            $video->private_disk ??= (string) config('filesystems.course_media', 'local');

            if (! filled($video->source_path) || ! $video->isDirty('source_path')) {
                return;
            }

            $disk = Storage::disk($video->private_disk);

            if (! $disk->exists($video->source_path)) {
                return;
            }

            $actualSize = (int) $disk->size($video->source_path);
            $chunkedUploadMetadata = ChunkedVideoUploadMetadata::pull(
                $video->private_disk,
                $video->source_path,
            );

            if (
                is_array($chunkedUploadMetadata)
                && (int) ($chunkedUploadMetadata['size_bytes'] ?? 0) === $actualSize
                && preg_match('/^[a-f0-9]{64}$/', (string) ($chunkedUploadMetadata['sha256'] ?? '')) === 1
            ) {
                $video->size_bytes = $actualSize;
                $video->sha256 = $chunkedUploadMetadata['sha256'];

                return;
            }

            $video->size_bytes = $actualSize;
            $stream = $disk->readStream($video->source_path);

            if (is_resource($stream)) {
                $context = hash_init('sha256');

                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);

                    if ($chunk === false) {
                        break;
                    }

                    hash_update($context, $chunk);
                }

                fclose($stream);
                $video->sha256 = hash_final($context);
            }
        });

        static::deleted(function (CourseVideo $video): void {
            $diskName = $video->private_disk ?: config('filesystems.course_media', 'local');
            $disk = Storage::disk($diskName);
            $paths = array_filter([
                $video->source_path,
                $video->hls_manifest_path,
                $video->thumbnail_url,
            ]);

            if (filled($video->source_path)) {
                ChunkedVideoUploadMetadata::forget((string) $diskName, $video->source_path);
            }

            if ($paths !== []) {
                $disk->delete($paths);
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(VideoProgress::class);
    }

    public function offlineDownloads(): HasMany
    {
        return $this->hasMany(OfflineDownload::class);
    }

    public function localized(string $field, ?string $locale = null): ?string
    {
        $values = (array) $this->{$field};
        $locale ??= app()->getLocale();

        return $values[$locale]
            ?? $values['ar']
            ?? $values[config('app.fallback_locale')]
            ?? null;
    }

    public function translated(string $field): ?string
    {
        return $this->localized($field);
    }
}
