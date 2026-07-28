<?php

namespace App\Models;

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
        'duration_seconds',
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
            'is_preview' => 'boolean',
            'is_downloadable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (CourseVideo $video): void {
            $disk = Storage::disk(config('filesystems.course_media', 'local'));
            $paths = array_filter([
                $video->source_path,
                $video->hls_manifest_path,
                $video->thumbnail_url,
            ]);

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
