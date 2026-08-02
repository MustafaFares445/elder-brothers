<?php

namespace Tests\Feature\Media;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\CourseVideo;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfflineVideoSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('filesystems.course_media', 'local');
        config()->set('elder.signed_url_ttl_minutes', 15);
        config()->set('elder.offline.max_devices_per_user', 3);
        Storage::fake('local');
    }

    public function test_signed_offline_download_url_expires(): void
    {
        [$user, $video] = $this->entitledUserAndVideo();
        $this->device($user, 'signed-device');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/videos/{$video->id}/offline-downloads", [
            'device_id' => 'signed-device',
        ])->assertCreated();

        $url = $response->json('data.file.url');
        $relative = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $this->travel(16)->minutes();

        $this->get($relative)->assertForbidden();
    }

    public function test_fourth_active_device_is_rejected(): void
    {
        $user = User::create([
            'full_name' => 'Device Limit User',
            'phone' => '+963900000778',
            'password' => 'Password123!',
            'status' => 'active',
        ]);

        foreach (range(1, 3) as $index) {
            $this->device($user, 'device-'.$index);
        }

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/devices/register', [
            'device_id' => 'device-4',
            'platform' => 'android',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['server_time']);
    }

    private function entitledUserAndVideo(): array
    {
        $user = User::create([
            'full_name' => 'Signed URL User',
            'phone' => '+963900000779',
            'password' => 'Password123!',
            'status' => 'active',
        ]);

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
            'slug' => 'signed-offline-course',
            'title' => ['ar' => 'دورة موقعة'],
            'description' => ['ar' => 'وصف'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $path = "courses/{$course->id}/videos/signed.mp4";
        Storage::disk('local')->put($path, 'signed-video-content');

        $video = CourseVideo::create([
            'course_id' => $course->id,
            'title' => ['ar' => 'فيديو موقّع'],
            'source_path' => $path,
            'duration_seconds' => 60,
            'sort_order' => 1,
            'status' => 'ready',
        ]);

        CourseSubscription::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'source' => 'qr',
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        return [$user, $video];
    }

    private function device(User $user, string $id): UserDevice
    {
        return UserDevice::create([
            'user_id' => $user->id,
            'device_id' => $id,
            'fcm_token' => '',
            'platform' => 'android',
            'notifications_enabled' => true,
            'last_seen_at' => now(),
        ]);
    }
}
