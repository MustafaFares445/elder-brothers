<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\QrSubscriptionController;
use App\Http\Controllers\Api\V1\SupportController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'success' => true,
    'code' => 'HEALTHY',
    'message' => 'OK',
    'data' => ['timestamp' => now()->toIso8601String()],
    'meta' => null,
]));

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('verify-reset-otp', [AuthController::class, 'verifyResetOtp']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('me', [ProfileController::class, 'show']);
        Route::patch('me', [ProfileController::class, 'update']);
        Route::post('me/avatar', [ProfileController::class, 'avatar']);
        Route::put('me/password', [ProfileController::class, 'password']);
        Route::put('me/preferences', [ProfileController::class, 'preferences']);
        Route::post('me/devices', [ProfileController::class, 'storeDevice']);
        Route::delete('me/devices/{device}', [ProfileController::class, 'destroyDevice']);

        Route::get('home', [CatalogController::class, 'home']);
        Route::get('academic-years', [CatalogController::class, 'academicYears']);
        Route::get('academic-years/{academicYear}/subjects', [CatalogController::class, 'subjects']);
        Route::get('subjects/{subject}/courses', [CatalogController::class, 'subjectCourses']);
        Route::get('courses', [CatalogController::class, 'courses']);
        Route::get('courses/{course}', [CatalogController::class, 'course']);
        Route::get('courses/{course}/content', [ContentController::class, 'courseContent']);

        Route::get('me/courses', [CatalogController::class, 'myCourses']);
        Route::get('me/subscriptions', [CatalogController::class, 'subscriptions']);
        Route::get('me/subscriptions/{subscription}', [CatalogController::class, 'subscription']);

        Route::post('subscriptions/qr/preview', [QrSubscriptionController::class, 'preview']);
        Route::post('subscriptions/qr/redeem', [QrSubscriptionController::class, 'redeem']);

        Route::post('videos/{video}/playback-url', [ContentController::class, 'playbackUrl']);
        Route::put('videos/{video}/progress', [ContentController::class, 'progress']);
        Route::post('videos/{video}/complete', [ContentController::class, 'complete']);
        Route::post('videos/{video}/download-url', [ContentController::class, 'videoDownloadUrl']);
        Route::post('course-files/{courseFile}/download-url', [ContentController::class, 'fileDownloadUrl']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);

        Route::post('support-requests', [SupportController::class, 'store']);
    });

    Route::get('content-pages/{slug}', [SupportController::class, 'page']);
});
