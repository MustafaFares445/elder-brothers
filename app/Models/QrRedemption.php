<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
