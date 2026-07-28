<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'suspended_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('suspended_at')->nullable()->index()->after('status');
            });
        }

        if (! Schema::hasColumn('users', 'suspension_reason')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->text('suspension_reason')->nullable()->after('suspended_at');
            });
        }

        if (! Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->enum('type', ['string', 'integer', 'float', 'boolean', 'json'])->default('string');
                $table->string('group')->default('general')->index();
                $table->string('label')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');

        if (Schema::hasColumn('users', 'suspension_reason')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('suspension_reason');
            });
        }

        if (Schema::hasColumn('users', 'suspended_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('suspended_at');
            });
        }
    }
};
