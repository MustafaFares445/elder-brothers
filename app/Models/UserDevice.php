<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'fcm_token',
        'platform',
        'app_version',
        'device_name',
        'notifications_enabled',
        'last_seen_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (UserDevice $device): void {
            if (! $device->user_id) {
                return;
            }

            $activeDevices = static::query()
                ->where('user_id', $device->user_id)
                ->whereNull('revoked_at')
                ->count();

            if ($activeDevices >= (int) config('elder.offline.max_devices_per_user', 3)) {
                throw ValidationException::withMessages([
                    'device_id' => ['تم تجاوز الحد الأقصى للأجهزة المسموح بها.'],
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offlineDownloads(): HasMany
    {
        return $this->hasMany(OfflineDownload::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
