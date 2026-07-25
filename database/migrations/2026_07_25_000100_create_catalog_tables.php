<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->json('title');
            $table->text('image_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['academic_year_id', 'sort_order']);
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->json('title');
            $table->json('description');
            $table->json('short_description')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->text('hero_url')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('course_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->json('title');
            $table->json('lesson_label')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->text('source_path');
            $table->text('hls_manifest_path')->nullable();
            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_preview')->default(false);
            $table->boolean('is_downloadable')->default(false);
            $table->enum('status', ['processing', 'ready', 'failed'])->default('processing')->index();
            $table->timestamps();
            $table->unique(['course_id', 'sort_order']);
        });

        Schema::create('course_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->json('title');
            $table->text('file_path')->nullable();
            $table->text('external_url')->nullable();
            $table->string('original_name');
            $table->string('mime_type');
            $table->string('extension', 20);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_downloadable')->default(true);
            $table->timestamps();
            $table->unique(['course_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_files');
        Schema::dropIfExists('course_videos');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('academic_years');
    }
};
