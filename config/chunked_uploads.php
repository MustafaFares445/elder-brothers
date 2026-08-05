<?php

return [
    // Keep every HTTP request small enough for typical PHP / reverse proxy limits.
    'chunk_size' => (int) env('VIDEO_UPLOAD_CHUNK_SIZE', 5 * 1024 * 1024),

    // Maximum accepted video size in bytes (20 GB by default).
    'max_file_size' => (int) env('VIDEO_UPLOAD_MAX_SIZE', 20 * 1024 * 1024 * 1024),

    // Unfinished and unreferenced completed sessions are removed by the cleanup command.
    'expire_after_hours' => (int) env('VIDEO_UPLOAD_SESSION_TTL_HOURS', 24),

    'allowed_client_mime_types' => [
        'video/mp4',
        'video/x-m4v',
        'application/mp4',
        'application/octet-stream',
    ],

    'allowed_detected_mime_types' => [
        'video/mp4',
        'application/mp4',
        'application/octet-stream',
    ],
];
