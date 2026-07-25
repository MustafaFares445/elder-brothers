<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
