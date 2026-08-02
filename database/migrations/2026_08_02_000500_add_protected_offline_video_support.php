<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table): void {
            $table->string('device_name')->nullable()->after('app_version');
            $table->timestamp('revoked_at')->nullable()->after('last_seen_at')->index();
        });

        Schema::table('course_videos', function (Blueprint $table): void {
            $table->string('private_disk')->default('local')->after('hls_manifest_path');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('duration_seconds');
            $table->string('sha256', 64)->nullable()->after('size_bytes')->index();
        });

        Schema::create('offline_downloads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_video_id')->constrained('course_videos')->cascadeOnDelete();
            $table->foreignId('user_device_id')->constrained('user_devices')->cascadeOnDelete();
            $table->string('status', 30)->default('created')->index();
            $table->timestamp('offline_expires_at')->index();
            $table->timestamp('refresh_after')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('revoke_reason')->nullable();
            $table->unsignedBigInteger('encrypted_size_bytes')->nullable();
            $table->string('encrypted_sha256', 64)->nullable();
            $table->string('algorithm', 100)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'course_video_id']);
            $table->index(['user_device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_downloads');

        Schema::table('course_videos', function (Blueprint $table): void {
            $table->dropIndex(['sha256']);
            $table->dropColumn(['private_disk', 'size_bytes', 'sha256']);
        });

        Schema::table('user_devices', function (Blueprint $table): void {
            $table->dropIndex(['revoked_at']);
            $table->dropColumn(['device_name', 'revoked_at']);
        });
    }
};
