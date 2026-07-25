<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name');
            $table->string('phone')->unique();
            $table->string('pending_phone')->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->timestamp('phone_verified_at')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active')->index();
            $table->boolean('is_admin')->default(false)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('phone')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('phone')->index();
            $table->enum('purpose', ['registration', 'password_reset', 'phone_change'])->index();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('resend_available_at');
            $table->timestamps();
            $table->index(['phone', 'purpose', 'created_at']);
        });

        Schema::create('user_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('locale', ['ar', 'en'])->default('ar');
            $table->boolean('smart_notifications')->default(true);
            $table->enum('download_quality', ['auto', 'hd', 'sd'])->default('auto');
            $table->timestamps();
        });

        Schema::create('user_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id');
            $table->text('fcm_token');
            $table->enum('platform', ['android', 'ios']);
            $table->string('app_version')->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_id']);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
