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

    public function test_playback_response_returns_signed_stream_url_and_stream_supports_exoplayer_ranges(): void
    {
        config()->set('filesystems.course_media', 'local');
        Storage::fake('local');

        $user = User::create([
            'full_name' => 'Streaming Student',
            'phone' => '+963900000099',
            'email' => 'streaming@example.com',
            'password' => 'Password123!',
            'status' => 'active',
        ]);

        $video = $this->createReadyVideo();
        Storage::disk('local')->put($video->source_path, '0123456789');

        CourseSubscription::create([
            'user_id' => $user->id,
            'course_id' => $video->course_id,
            'source' => 'qr',
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

        $this->assertStreamedRange(
            $relativeStreamUrl,
            'bytes=0-',
            206,
            [
                'Accept-Ranges' => 'bytes',
                'Content-Length' => '10',
                'Content-Range' => 'bytes 0-9/10',
            ],
            '0123456789',
        );

        $this->assertStreamedRange(
            $relativeStreamUrl,
            'bytes=4-',
            206,
            [
                'Content-Length' => '6',
                'Content-Range' => 'bytes 4-9/10',
            ],
            '456789',
        );

        $this->assertStreamedRange(
            $relativeStreamUrl,
            'Bytes = 2 - 5',
            206,
            [
                'Content-Length' => '4',
                'Content-Range' => 'bytes 2-5/10',
            ],
            '2345',
        );

        $this->assertStreamedRange(
            $relativeStreamUrl,
            'bytes=-3',
            206,
            ['Content-Range' => 'bytes 7-9/10'],
            '789',
        );

        $this->assertStreamedRange(
            $relativeStreamUrl,
            'bytes=8-999',
            206,
            ['Content-Range' => 'bytes 8-9/10'],
            '89',
        );

        $fullResponse = $this->get($relativeStreamUrl, ['Range' => 'items=0-1']);
        $fullResponse
            ->assertOk()
            ->assertHeader('Content-Length', '10')
            ->assertHeaderMissing('Content-Range');
        $this->assertSame('0123456789', $fullResponse->streamedContent());

        $this->get($relativeStreamUrl, ['Range' => 'bytes=10-'])
            ->assertStatus(416)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes */10');
    }

    public function test_stream_endpoint_rejects_missing_or_invalid_signature(): void
    {
        config()->set('filesystems.course_media', 'local');
        Storage::fake('local');

        $video = $this->createReadyVideo();
        Storage::disk('local')->put($video->source_path, '0123456789');

        $this->get("/api/v1/videos/{$video->id}/stream")
            ->assertForbidden();
    }

    private function createReadyVideo(): CourseVideo
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

        $course = Course::create([
            'subject_id' => $subject->id,
            'slug' => 'private-streaming-course',
            'title' => ['ar' => 'دورة خاصة'],
            'description' => ['ar' => 'وصف'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        return CourseVideo::create([
            'course_id' => $course->id,
            'title' => ['ar' => 'فيديو'],
            'source_path' => "courses/{$course->id}/videos/sample.mp4",
            'duration_seconds' => 60,
            'sort_order' => 1,
            'status' => 'ready',
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function assertStreamedRange(
        string $url,
        string $range,
        int $status,
        array $headers,
        string $expectedContent,
    ): void {
        $response = $this->get($url, ['Range' => $range]);
        $response->assertStatus($status);

        foreach ($headers as $name => $value) {
            $response->assertHeader($name, $value);
        }

        $this->assertSame($expectedContent, $response->streamedContent());
    }
}
