<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrRedemption extends Model
{
    protected $fillable = [
        'subscription_qr_code_id',
        'user_id',
        'course_subscription_id',
        'redeemed_at',
        'ip_address',
        'device_id',
    ];

    protected function casts(): array
    {
        return [
            'redeemed_at' => 'datetime',
        ];
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(SubscriptionQrCode::class, 'subscription_qr_code_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CourseSubscription::class, 'course_subscription_id');
    }
}
