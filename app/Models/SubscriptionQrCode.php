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
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(QrRedemption::class);
    }
}
