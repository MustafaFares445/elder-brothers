<?php

return [
    'otp' => [
        'driver' => env('OTP_DRIVER', 'log'),
        'fixed_code' => env('OTP_FIXED_CODE'),
        'expires_minutes' => (int) env('OTP_EXPIRES_MINUTES', 5),
        'resend_seconds' => (int) env('OTP_RESEND_SECONDS', 60),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    ],
    'fcm' => [
        'driver' => env('FCM_DRIVER', 'log'),
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials' => env('FCM_CREDENTIALS'),
    ],
    'media' => [
        'private_disk' => env('PRIVATE_MEDIA_DISK', 'local'),
        'signed_url_ttl_minutes' => (int) env('SIGNED_URL_TTL_MINUTES', 10),
    ],
];
