<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may use the composite unique index as the supporting index for
        // the course_id foreign key. Add an explicit foreign-key index first,
        // in a separate ALTER TABLE statement, before removing the unique one.
        Schema::table('course_videos', function (Blueprint $table): void {
            $table->index('course_id', 'course_videos_course_id_index');
        });

        Schema::table('course_videos', function (Blueprint $table): void {
            $table->dropUnique('course_videos_course_id_sort_order_unique');
            $table->index(
                ['course_id', 'sort_order'],
                'course_videos_course_sort_order_index',
            );
        });

        Schema::table('course_files', function (Blueprint $table): void {
            $table->index('course_id', 'course_files_course_id_index');
        });

        Schema::table('course_files', function (Blueprint $table): void {
            $table->dropUnique('course_files_course_id_sort_order_unique');
            $table->index(
                ['course_id', 'sort_order'],
                'course_files_course_sort_order_index',
            );
        });
    }

    public function down(): void
    {
        // Restore the unique indexes before removing the replacement indexes,
        // so the foreign keys always retain a valid supporting index.
        Schema::table('course_videos', function (Blueprint $table): void {
            $table->unique(
                ['course_id', 'sort_order'],
                'course_videos_course_id_sort_order_unique',
            );
        });

        Schema::table('course_videos', function (Blueprint $table): void {
            $table->dropIndex('course_videos_course_sort_order_index');
            $table->dropIndex('course_videos_course_id_index');
        });

        Schema::table('course_files', function (Blueprint $table): void {
            $table->unique(
                ['course_id', 'sort_order'],
                'course_files_course_id_sort_order_unique',
            );
        });

        Schema::table('course_files', function (Blueprint $table): void {
            $table->dropIndex('course_files_course_sort_order_index');
            $table->dropIndex('course_files_course_id_index');
        });
    }
};
