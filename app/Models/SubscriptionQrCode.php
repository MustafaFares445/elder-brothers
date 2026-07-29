<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionQrCode extends Model
{
    protected $fillable = [
        'course_id',
        'code_hash',
        'code_encrypted',
        'code_hint',
        'label',
        'starts_at',
        'expires_at',
        'max_redemptions',
        'redemptions_count',
        'subscription_duration_days',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'code_encrypted' => 'encrypted',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SubscriptionQrCode $qrCode): void {
            $qrCode->starts_at ??= now();
            $qrCode->expires_at = $qrCode->starts_at->copy()->addDays(2);
            $qrCode->max_redemptions = 1;
        });

        static::saving(function (SubscriptionQrCode $qrCode): void {
            $qrCode->max_redemptions = 1;
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(QrRedemption::class);
    }
}
