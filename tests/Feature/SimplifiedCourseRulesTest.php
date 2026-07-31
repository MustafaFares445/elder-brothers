<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseSubscription;
use App\Models\CourseVideo;
use App\Models\Subject;
use App\Models\User;
use App\Services\SubscriptionQrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimplifiedCourseRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_slug_media_rules_and_sort_orders_are_applied_automatically(): void
    {
        $year = AcademicYear::query()->create([
            'title' => ['ar' => 'السنة الأولى'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subject = Subject::query()->create([
            'academic_year_id' => $year->id,
            'title' => ['ar' => 'الرياضيات'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'subject_id' => $subject->id,
            'title' => ['ar' => 'الرياضيات المتقدمة'],
            'description' => ['ar' => 'شرح الدورة'],
            'status' => 'draft',
        ]);

        $video = CourseVideo::query()->create([
            'course_id' => $course->id,
            'title' => ['ar' => 'المحاضرة الأولى'],
            'source_path' => 'courses/1/videos/video-1.mp4',
            'duration_seconds' => 120,
            'sort_order' => 1,
            'is_preview' => true,
            'is_downloadable' => false,
            'status' => 'ready',
        ]);

        $secondVideo = CourseVideo::query()->create([
            'course_id' => $course->id,
            'title' => ['ar' => 'المحاضرة الثانية'],
            'source_path' => 'courses/1/videos/video-2.mp4',
            'duration_seconds' => 180,
            'sort_order' => 1,
            'status' => 'ready',
        ]);

        $file = CourseFile::query()->create([
            'course_id' => $course->id,
            'title' => ['ar' => 'ملف الدورة'],
            'external_url' => 'https://example.com/course-1.pdf',
            'original_name' => 'course-1.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 100,
            'sort_order' => 1,
            'is_downloadable' => false,
        ]);

        $secondFile = CourseFile::query()->create([
            'course_id' => $course->id,
            'title' => ['ar' => 'ملف الدورة الثاني'],
            'external_url' => 'https://example.com/course-2.pdf',
            'original_name' => 'course-2.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 200,
            'sort_order' => 1,
        ]);

        $this->assertNotEmpty($course->slug);
        $this->assertFalse($video->fresh()->is_preview);
        $this->assertTrue($video->fresh()->is_downloadable);
        $this->assertTrue($file->fresh()->is_downloadable);
        $this->assertSame(1, $video->fresh()->sort_order);
        $this->assertSame(2, $secondVideo->fresh()->sort_order);
        $this->assertSame(1, $file->fresh()->sort_order);
        $this->assertSame(2, $secondFile->fresh()->sort_order);
    }

    public function test_qr_code_is_encrypted_single_use_and_uses_selected_expiration_date(): void
    {
        $year = AcademicYear::query()->create([
            'title' => ['ar' => 'السنة الأولى'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subject = Subject::query()->create([
            'academic_year_id' => $year->id,
            'title' => ['ar' => 'الفيزياء'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'subject_id' => $subject->id,
            'title' => ['ar' => 'الفيزياء'],
            'description' => ['ar' => 'شرح الدورة'],
            'status' => 'published',
        ]);

        $expiresAt = now()->addYear()->startOfMinute();

        [$qrCode, $rawCode] = app(SubscriptionQrCodeService::class)->create([
            'course_id' => $course->id,
            'label' => 'كود اختبار',
            'subscription_duration_days' => 365,
            'max_redemptions' => 99,
            'expires_at' => $expiresAt,
        ], 'ELDER-TEST-SINGLE-USE', null);

        $this->assertSame('ELDER-TEST-SINGLE-USE', $rawCode);
        $this->assertSame($rawCode, $qrCode->fresh()->code_encrypted);
        $this->assertSame(1, $qrCode->fresh()->max_redemptions);
        $this->assertTrue($qrCode->fresh()->expires_at->equalTo($expiresAt));
    }

    public function test_subscription_source_is_always_qr(): void
    {
        $user = User::factory()->create();

        $year = AcademicYear::query()->create([
            'title' => ['ar' => 'السنة الأولى'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $subject = Subject::query()->create([
            'academic_year_id' => $year->id,
            'title' => ['ar' => 'الكيمياء'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'subject_id' => $subject->id,
            'title' => ['ar' => 'الكيمياء'],
            'description' => ['ar' => 'شرح الدورة'],
            'status' => 'published',
        ]);

        $subscription = CourseSubscription::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'source' => 'admin',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $this->assertSame('qr', $subscription->fresh()->source);
    }
}
