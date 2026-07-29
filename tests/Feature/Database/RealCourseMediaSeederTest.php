<?php

namespace Tests\Feature\Database;

use App\Models\Course;
use App\Models\User;
use Database\Seeders\RealCourseMediaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RealCourseMediaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_real_private_video_and_pdf_files(): void
    {
        config()->set('filesystems.course_media', 'local');
        Storage::fake('local');

        $this->seed(RealCourseMediaSeeder::class);

        $course = Course::query()
            ->where('slug', 'real-private-media-course')
            ->with(['videos', 'files'])
            ->firstOrFail();

        $this->assertCount(2, $course->videos);
        $this->assertCount(2, $course->files);

        foreach ($course->videos as $video) {
            Storage::disk('local')->assertExists($video->source_path);
            $contents = Storage::disk('local')->get($video->source_path);

            $this->assertStringContainsString('ftyp', substr($contents, 0, 64));
            $this->assertSame('ready', $video->status);
            $this->assertFalse($video->is_preview);
            $this->assertTrue($video->is_downloadable);
        }

        foreach ($course->files as $file) {
            Storage::disk('local')->assertExists($file->file_path);
            $contents = Storage::disk('local')->get($file->file_path);

            $this->assertStringStartsWith('%PDF-', $contents);
            $this->assertSame('application/pdf', $file->mime_type);
            $this->assertSame('pdf', $file->extension);
            $this->assertGreaterThan(0, $file->size_bytes);
            $this->assertTrue($file->is_downloadable);
        }

        $student = User::query()->where('phone', '+963900000100')->firstOrFail();

        $this->assertTrue($student->subscriptions()
            ->where('course_id', $course->id)
            ->where('source', 'qr')
            ->where('status', 'active')
            ->exists());
    }

    public function test_seeder_repairs_missing_media_paths_without_overwriting_real_files(): void
    {
        config()->set('filesystems.course_media', 'local');
        Storage::fake('local');

        $this->seed(RealCourseMediaSeeder::class);

        $course = Course::query()->where('slug', 'real-private-media-course')->firstOrFail();
        $video = $course->videos()->firstOrFail();
        $file = $course->files()->firstOrFail();

        Storage::disk('local')->delete([$video->source_path, $file->file_path]);

        $this->seed(RealCourseMediaSeeder::class);

        Storage::disk('local')->assertExists($video->fresh()->source_path);
        Storage::disk('local')->assertExists($file->fresh()->file_path);
    }
}
