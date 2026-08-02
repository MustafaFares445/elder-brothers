<?php

namespace Tests\Feature\Media;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\CourseVideo;
use App\Models\OfflineDownload;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfflineVideoProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('filesystems.course_media', 'local');
        config()->set('elder.offline.max_devices_per_user', 3);
        config()->set('elder.offline.max_downloads_per_video', 2);
        Storage::fake('local');
    }

    public function test_user_can_complete_the_protected_offline_download_lifecycle(): void
    {
        [$user, $video] = $this->entitledUserAndVideo();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/devices/register', [
            'device_id' => 'device-one',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'device_name' => 'Test Android',
        ])
            ->assertOk()
            ->assertJsonPath('data.device_id', 'device-one')
            ->assertJsonStructure(['server_time']);

        $created = $this->postJson("/api/v1/videos/{$video->id}/offline-downloads", [
            'device_id' => 'device-one',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('code', 'OFFLINE_DOWNLOAD_CREATED')
            ->assertJsonPath('data.file.size_bytes', 10)
            ->assertJsonPath('data.file.sha256', hash('sha256', '0123456789'))
            ->assertJsonPath('data.file.mime', 'video/mp4')
            ->assertJsonPath('data.license.can_play_offline', true)
            ->assertJsonStructure([
                'data' => [
                    'download_id',
                    'file' => ['url', 'expires_at'],
                    'license' => ['offline_expires_at', 'refresh_after'],
                ],
                'server_time',
            ]);

        $downloadId = $created->json('data.download_id');

        $this->postJson("/api/v1/offline-downloads/{$downloadId}/complete", [
            'encrypted_size_bytes' => 32,
            'encrypted_sha256' => str_repeat('a', 64),
            'algorithm' => 'AES-256-CTR+HMAC-SHA256',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonStructure(['server_time']);

        $this->postJson("/api/v1/offline-downloads/{$downloadId}/refresh")
            ->assertOk()
            ->assertJsonPath('data.revoked', false)
            ->assertJsonStructure(['server_time']);

        $this->deleteJson("/api/v1/offline-downloads/{$downloadId}")
            ->assertOk()
            ->assertJsonPath('code', 'OFFLINE_DOWNLOAD_DELETED');

        $this->assertDatabaseHas('offline_downloads', [
            'id' => $downloadId,
            'status' => 'deleted',
            'revoke_reason' => 'user_deleted',
        ]);
    }

    public function test_user_without_entitlement_cannot_create_offline_download(): void
    {
        [$user, $video] = $this->entitledUserAndVideo(false);
        $device = $this->device($user, 'no-access-device');
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/videos/{$video->id}/offline-downloads", [
            'device_id' => $device->device_id,
        ])->assertForbidden();
    }

    public function test_revoked_device_cannot_create_offline_download(): void
    {
        [$user, $video] = $this->entitledUserAndVideo();
        $device = $this->device($user, 'revoked-device', now());
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/videos/{$video->id}/offline-downloads", [
            'device_id' => $device->device_id,
        ])->assertUnprocessable();
    }

    public function test_complete_rejects_a_download_owned_by_another_user(): void
    {
        [$owner, $video] = $this->entitledUserAndVideo();
        $device = $this->device($owner, 'owner-device');

        $download = OfflineDownload::create([
            'user_id' => $owner->id,
            'course_video_id' => $video->id,
            'user_device_id' => $device->id,
            'status' => 'created',
            'offline_expires_at' => now()->addDays(30),
            'refresh_after' => now()->addDays(20),
        ]);

        $other = User::create([
            'full_name' => 'Other User',
            'phone' => '+963911111111',
            'password' => 'Password123!',
            'status' => 'active',
        ]);
        Sanctum::actingAs($other);

        $this->postJson("/api/v1/offline-downloads/{$download->id}/complete", [
            'encrypted_size_bytes' => 32,
            'encrypted_sha256' => str_repeat('b', 64),
            'algorithm' => 'AES-256-CTR+HMAC-SHA256',
        ])->assertForbidden();
    }

    public function test_refresh_revokes_license_after_subscription_expiration(): void
    {
        [$user, $video, $subscription] = $this->entitledUserAndVideo();
        $device = $this->device($user, 'expired-subscription-device');
        $download = OfflineDownload::create([
            'user_id' => $user->id,
            'course_video_id' => $video->id,
            'user_device_id' => $device->id,
            'status' => 'completed',
            'offline_expires_at' => now()->addDays(30),
            'refresh_after' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        $subscription->update([
            'expires_at' => now()->subMinute(),
            'status' => 'expired',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/offline-downloads/{$download->id}/refresh")
            ->assertOk()
            ->assertJsonPath('data.revoked', true)
            ->assertJsonPath('data.reason', 'subscription_expired');
    }

    public function test_download_limit_is_enforced_per_video(): void
    {
        [$user, $video] = $this->entitledUserAndVideo();
        $first = $this->device($user, 'limit-device-one');
        $second = $this->device($user, 'limit-device-two');
        $third = $this->device($user, 'limit-device-three');
        Sanctum::actingAs($user);

        foreach ([$first, $second] as $device) {
            $this->postJson("/api/v1/videos/{$video->id}/offline-downloads", [
                'device_id' => $device->device_id,
            ])->assertCreated();
        }

        $this->postJson("/api/v1/videos/{$video->id}/offline-downloads", [
            'device_id' => $third->device_id,
        ])->assertUnprocessable();
    }

    private function entitledUserAndVideo(bool $subscribe = true): array
    {
        $user = User::create([
            'full_name' => 'Offline Student',
            'phone' => '+963900000777',
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
            'slug' => 'offline-protected-course',
            'title' => ['ar' => 'دورة أوفلاين'],
            'description' => ['ar' => 'وصف'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $path = "courses/{$course->id}/videos/offline.mp4";
        Storage::disk('local')->put($path, '0123456789');

        $video = CourseVideo::create([
            'course_id' => $course->id,
            'title' => ['ar' => 'فيديو أوفلاين'],
            'source_path' => $path,
            'duration_seconds' => 60,
            'sort_order' => 1,
            'status' => 'ready',
        ]);

        $subscription = null;

        if ($subscribe) {
            $subscription = CourseSubscription::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'source' => 'qr',
                'starts_at' => now()->subMinute(),
                'expires_at' => now()->addMonth(),
                'status' => 'active',
            ]);
        }

        return [$user, $video, $subscription];
    }

    private function device(User $user, string $deviceId, $revokedAt = null): UserDevice
    {
        return UserDevice::create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'fcm_token' => '',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'device_name' => 'Test Device',
            'notifications_enabled' => true,
            'last_seen_at' => now(),
            'revoked_at' => $revokedAt,
        ]);
    }
}
