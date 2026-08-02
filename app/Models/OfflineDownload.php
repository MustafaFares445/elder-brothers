<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineDownload extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'course_video_id',
        'user_device_id',
        'status',
        'offline_expires_at',
        'refresh_after',
        'completed_at',
        'revoked_at',
        'revoke_reason',
        'encrypted_size_bytes',
        'encrypted_sha256',
        'algorithm',
    ];

    protected function casts(): array
    {
        return [
            'offline_expires_at' => 'datetime',
            'refresh_after' => 'datetime',
            'completed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(CourseVideo::class, 'course_video_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'user_device_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null || in_array($this->status, ['revoked', 'deleted'], true);
    }
}
