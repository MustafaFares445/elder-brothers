<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
    ];

    public static function value(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $setting->value,
            'float' => (float) $setting->value,
            'json' => json_decode((string) $setting->value, true),
            default => $setting->value,
        };
    }

    public static function put(string $key, mixed $value, string $type = 'string', string $group = 'general', ?string $label = null): void
    {
        if ($type === 'boolean') {
            $value = $value ? '1' : '0';
        } elseif ($type === 'json') {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'type' => $type,
                'group' => $group,
                'label' => $label,
            ],
        );

        cache()->forget("platform-setting:{$key}");
    }
}
