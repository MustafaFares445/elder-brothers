<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'source',
        'starts_at',
        'expires_at',
        'revoked_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CourseSubscription $subscription): void {
            $subscription->source = 'qr';
        });

        static::updated(function (CourseSubscription $subscription): void {
            if (! $subscription->wasChanged(['status', 'revoked_at', 'expires_at'])) {
                return;
            }

            if ($subscription->isActive()) {
                return;
            }

            OfflineDownload::query()
                ->where('user_id', $subscription->user_id)
                ->whereNull('revoked_at')
                ->whereHas('video', fn ($query) => $query->where('course_id', $subscription->course_id))
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'revoke_reason' => 'subscription_expired',
                ]);
        });

        static::deleted(function (CourseSubscription $subscription): void {
            OfflineDownload::query()
                ->where('user_id', $subscription->user_id)
                ->whereNull('revoked_at')
                ->whereHas('video', fn ($query) => $query->where('course_id', $subscription->course_id))
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'revoke_reason' => 'subscription_revoked',
                ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ! $this->revoked_at
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
