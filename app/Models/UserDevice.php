<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
