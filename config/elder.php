<?php

return [
    'otp' => [
        'driver' => env('OTP_DRIVER', 'log'),
        'length' => (int) env('OTP_LENGTH', 6),
        'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 5),
        'resend_seconds' => (int) env('OTP_RESEND_SECONDS', 60),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    ],
    'signed_url_ttl_minutes' => (int) env('SIGNED_URL_TTL_MINUTES', 15),
    'video_completion_percentage' => (int) env('VIDEO_COMPLETION_PERCENTAGE', 90),
];
