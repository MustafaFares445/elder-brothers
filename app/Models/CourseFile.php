<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CourseFile extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'file_path',
        'external_url',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'sort_order',
        'is_downloadable',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'is_downloadable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CourseFile $file): void {
            if ($file->file_path) {
                $disk = Storage::disk(config('filesystems.course_media', 'local'));

                if ($disk->exists($file->file_path)) {
                    $file->original_name = $file->original_name ?: basename($file->file_path);
                    $file->mime_type = $disk->mimeType($file->file_path) ?: 'application/pdf';
                    $file->extension = pathinfo($file->original_name, PATHINFO_EXTENSION) ?: 'pdf';
                    $file->size_bytes = $disk->size($file->file_path);
                }

                $file->external_url = null;

                return;
            }

            if ($file->external_url) {
                $path = (string) parse_url($file->external_url, PHP_URL_PATH);
                $name = basename($path);

                $file->original_name = $file->original_name ?: ($name ?: 'external-file');
                $file->extension = pathinfo($file->original_name, PATHINFO_EXTENSION) ?: 'link';
                $file->mime_type = $file->mime_type ?: 'application/octet-stream';
                $file->size_bytes = $file->size_bytes ?: 0;
            }
        });

        static::deleted(function (CourseFile $file): void {
            if ($file->file_path) {
                Storage::disk(config('filesystems.course_media', 'local'))->delete($file->file_path);
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function localized(string $field, ?string $locale = null): ?string
    {
        $values = $this->{$field};
        $locale ??= app()->getLocale();

        return $values[$locale]
            ?? $values[config('app.fallback_locale')]
            ?? null;
    }

    public function translated(string $field): ?string
    {
        return $this->localized($field);
    }
}
