<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->enum('source', ['qr', 'admin']);
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->enum('status', ['active', 'expired', 'revoked'])->default('active')->index();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
            $table->index(['user_id', 'course_id', 'status']);
        });

        Schema::create('video_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_video_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_watched_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_video_id']);
        });

        Schema::create('subscription_qr_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->string('code_hint', 191)->nullable();
            $table->string('label')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->unsignedInteger('subscription_duration_days')->nullable();
            $table->enum('status', ['active', 'disabled', 'exhausted', 'expired'])->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('qr_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_qr_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_subscription_id')->constrained()->cascadeOnDelete();
            $table->timestamp('redeemed_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('device_id')->nullable();
            $table->timestamps();
            $table->unique(['subscription_qr_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_redemptions');
        Schema::dropIfExists('subscription_qr_codes');
        Schema::dropIfExists('video_progress');
        Schema::dropIfExists('course_subscriptions');
    }
};
