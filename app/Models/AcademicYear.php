<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'subtitle' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
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
