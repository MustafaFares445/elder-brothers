<?php

use App\Http\Controllers\Admin\ChunkedVideoUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'status' => 'running',
]));

Route::middleware(['auth', 'throttle:600,1'])
    ->prefix('admin/video-uploads')
    ->name('admin.video-uploads.')
    ->controller(ChunkedVideoUploadController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
        Route::get('/{upload}', 'show')->whereUuid('upload')->name('show');
        Route::post('/{upload}/chunks', 'chunk')->whereUuid('upload')->name('chunks.store');
        Route::post('/{upload}/complete', 'complete')->whereUuid('upload')->name('complete');
        Route::delete('/{upload}', 'destroy')->whereUuid('upload')->name('destroy');
    });
