<?php

namespace Tests\Feature\Media;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\CourseVideo;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivateMediaStreamingTest extends TestCase
{
    use RefreshDatabase;

    public function test_playback_response_returns_signed_stream_url_and_stream_supports_ranges(): void
    {
        config()->set('filesystems.course_media', 'local');
        Storage::fake('local');

        $user = User::create([
            'full_name' => 'Streaming Student',
            'phone' => '+963900000099',
            'email' => 'streaming@example.com',
            'password' => 'Password123!',
            'phone_verified_at' => now(),
            'status' => 'active',
        ]);

        $year = AcademicYear::create([
            'title' => ['ar' => 'السنة الأولى', 'en' => 'First Year'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'academic_year_id' => $year->id,
            'title' => ['ar' => 'الرياضيات', 'en' => 'Mathematics'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $course = Course::create([
            'subject_id' => $subject->id,
            'slug' => 'private-streaming-course',
            'title' => ['ar' => 'دورة خاصة', 'en' => 'Private Course'],
            'description' => ['ar' => 'وصف', 'en' => 'Description'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $path = "courses/{$course->id}/videos/sample.mp4";
        Storage::disk('local')->put($path, '0123456789');

        $video = CourseVideo::create([
            'course_id' => $course->id,
            'title' => ['ar' => 'فيديو', 'en' => 'Video'],
            'source_path' => $path,
            'duration_seconds' => 60,
            'sort_order' => 1,
            'is_preview' => false,
            'is_downloadable' => true,
            'status' => 'ready',
        ]);

        CourseSubscription::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'source' => 'admin',
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDay(),
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $playback = $this->postJson("/api/v1/videos/{$video->id}/playback-url", [
            'resume' => false,
        ]);

        $playback
            ->assertOk()
            ->assertJsonPath('code', 'PLAYBACK_AUTHORIZED')
            ->assertJsonPath('data.storage', 'private_local')
            ->assertJsonPath('data.supports_range', true)
            ->assertJsonStructure([
                'data' => [
                    'playback_url',
                    'stream_url',
                    'signature',
                    'expires_at',
                ],
            ]);

        $streamUrl = $playback->json('data.stream_url');
        $relativeStreamUrl = parse_url($streamUrl, PHP_URL_PATH).'?'.parse_url($streamUrl, PHP_URL_QUERY);

        $this->get($relativeStreamUrl, ['Range' => 'bytes=2-5'])
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes 2-5/10')
            ->assertContent('2345');
    }

    public function test_stream_endpoint_rejects_missing_or_invalid_signature(): void
    {
        $this->get('/api/v1/videos/999/stream')->assertForbidden();
    }
}
