<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Course extends Model
{
    protected $fillable = [
        'subject_id',
        'slug',
        'title',
        'description',
        'short_description',
        'thumbnail_url',
        'hero_url',
        'status',
        'is_featured',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'short_description' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Course $course): void {
            if (blank($course->slug)) {
                $title = data_get($course->title, 'ar', '');
                $base = Str::slug((string) $title);

                if (blank($base)) {
                    $base = 'course';
                }

                do {
                    $slug = $base.'-'.Str::lower(Str::random(8));
                } while (static::query()->where('slug', $slug)->exists());

                $course->slug = $slug;
            }
        });

        static::deleted(function (Course $course): void {
            $paths = array_filter([$course->thumbnail_url, $course->hero_url]);

            if ($paths !== []) {
                Storage::disk(config('filesystems.course_media', 'local'))->delete($paths);
            }
        });
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(CourseVideo::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(CourseFile::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CourseSubscription::class);
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

    public function localizedTitle(?string $locale = null): ?string
    {
        return $this->localized('title', $locale);
    }

    public function translated(string $field): ?string
    {
        return $this->localized($field);
    }
}
