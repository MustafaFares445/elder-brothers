<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_videos', function (Blueprint $table): void {
            $table->dropUnique('course_videos_course_id_sort_order_unique');
            $table->index(['course_id', 'sort_order'], 'course_videos_course_sort_order_index');
        });

        Schema::table('course_files', function (Blueprint $table): void {
            $table->dropUnique('course_files_course_id_sort_order_unique');
            $table->index(['course_id', 'sort_order'], 'course_files_course_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('course_videos', function (Blueprint $table): void {
            $table->dropIndex('course_videos_course_sort_order_index');
            $table->unique(['course_id', 'sort_order']);
        });

        Schema::table('course_files', function (Blueprint $table): void {
            $table->dropIndex('course_files_course_sort_order_index');
            $table->unique(['course_id', 'sort_order']);
        });
    }
};
