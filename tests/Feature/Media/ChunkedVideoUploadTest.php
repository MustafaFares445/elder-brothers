<?php

namespace Tests\Feature\Media;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseVideo;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChunkedVideoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_and_complete_an_mp4_in_chunks(): void
    {
        Storage::fake('local');
        config()->set('filesystems.course_media', 'local');
        config()->set('chunked_uploads.chunk_size', 8);
        config()->set('chunked_uploads.max_file_size', 1024);

        $admin = $this->createUser(isAdmin: true);
        $course = $this->createCourse();
        $content = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";

        $session = $this->actingAs($admin)->postJson(route('admin.video-uploads.store'), [
            'course_id' => $course->id,
            'name' => 'lesson.mp4',
            'size' => strlen($content),
            'mime' => 'video/mp4',
        ]);

        $session
            ->assertCreated()
            ->assertJsonPath('chunk_size', 8)
            ->assertJsonPath('next_chunk', 0);

        $uploadId = $session->json('upload_id');
        $chunks = str_split($content, 8);

        foreach ($chunks as $index => $chunk) {
            $this->post(route('admin.video-uploads.chunks.store', $uploadId), [
                'chunk_index' => $index,
                'chunk' => UploadedFile::fake()->createWithContent("chunk-{$index}.part", $chunk),
            ])
                ->assertOk()
                ->assertJsonPath('next_chunk', $index + 1);
        }

        $completed = $this->postJson(route('admin.video-uploads.complete', $uploadId));

        $completed
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('progress', 100);

        $sourcePath = $completed->json('source_path');
        Storage::disk('local')->assertExists($sourcePath);
        $this->assertSame($content, Storage::disk('local')->get($sourcePath));

        $video = CourseVideo::create([
            'course_id' => $course->id,
            'title' => ['ar' => 'فيديو'],
            'source_path' => $sourcePath,
            'duration_seconds' => 60,
            'status' => 'processing',
        ]);

        $this->assertSame(strlen($content), $video->size_bytes);
        $this->assertSame(hash('sha256', $content), $video->sha256);
    }

    public function test_non_admin_cannot_create_an_upload_session(): void
    {
        $user = $this->createUser(isAdmin: false);
        $course = $this->createCourse();

        $this->actingAs($user)->postJson(route('admin.video-uploads.store'), [
            'course_id' => $course->id,
            'name' => 'lesson.mp4',
            'size' => 24,
            'mime' => 'video/mp4',
        ])->assertForbidden();
    }

    public function test_chunks_must_be_uploaded_in_order(): void
    {
        Storage::fake('local');
        config()->set('chunked_uploads.chunk_size', 8);
        config()->set('chunked_uploads.max_file_size', 1024);

        $admin = $this->createUser(isAdmin: true);
        $course = $this->createCourse();

        $session = $this->actingAs($admin)->postJson(route('admin.video-uploads.store'), [
            'course_id' => $course->id,
            'name' => 'lesson.mp4',
            'size' => 16,
            'mime' => 'video/mp4',
        ]);

        $this->post(route('admin.video-uploads.chunks.store', $session->json('upload_id')), [
            'chunk_index' => 1,
            'chunk' => UploadedFile::fake()->createWithContent('chunk-1.part', '12345678'),
        ])->assertStatus(409);
    }

    private function createUser(bool $isAdmin): User
    {
        return User::create([
            'full_name' => $isAdmin ? 'Upload Admin' : 'Regular User',
            'phone' => $isAdmin ? '+963900000201' : '+963900000202',
            'email' => $isAdmin ? 'upload-admin@example.com' : 'upload-user@example.com',
            'password' => 'Password123!',
            'status' => 'active',
            'is_admin' => $isAdmin,
        ]);
    }

    private function createCourse(): Course
    {
        $year = AcademicYear::create([
            'title' => ['ar' => 'السنة الأولى'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'academic_year_id' => $year->id,
            'title' => ['ar' => 'الرياضيات'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Course::create([
            'subject_id' => $subject->id,
            'slug' => 'chunked-video-course',
            'title' => ['ar' => 'دورة الفيديو'],
            'description' => ['ar' => 'وصف'],
            'status' => 'draft',
        ]);
    }
}
