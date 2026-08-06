<?php

namespace Tests\Support;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\CourseVideo;
use App\Models\Subject;
use App\Models\SubscriptionQrCode;
use App\Models\User;

trait CreatesLearningFixtures
{
    /**
     * @return array{
     *     active_user: User,
     *     subscriber: User,
     *     suspended_user: User,
     *     courses: array<int, Course>,
     *     subscribed_course: Course,
     *     subscribed_video: CourseVideo,
     *     subscription: CourseSubscription
     * }
     */
    protected function createLearningFixture(): array
    {
        $activeUser = $this->createFixtureUser('+963900000001');
        $subscriber = $this->createFixtureUser('+963900000002');
        $suspendedUser = $this->createFixtureUser('+963900000003', 'suspended');

        $yearTitles = [
            'السنة الأولى',
            'السنة الثانية',
            'السنة الثالثة',
            'السنة الرابعة',
        ];

        $courses = [];

        foreach ($yearTitles as $index => $yearTitle) {
            $position = $index + 1;

            $year = AcademicYear::query()->create([
                'title' => ['ar' => $yearTitle, 'en' => "Year {$position}"],
                'subtitle' => ['ar' => "وصف {$yearTitle}", 'en' => "Year {$position} description"],
                'icon' => 'school',
                'sort_order' => $position,
                'is_active' => true,
            ]);

            $subject = Subject::query()->create([
                'academic_year_id' => $year->id,
                'title' => ['ar' => "المادة {$position}", 'en' => "Subject {$position}"],
                'image_url' => null,
                'sort_order' => 1,
                'is_active' => true,
            ]);

            $course = Course::query()->create([
                'subject_id' => $subject->id,
                'slug' => "fixture-course-{$position}",
                'title' => ['ar' => "الدورة {$position}", 'en' => "Course {$position}"],
                'short_description' => ['ar' => 'وصف مختصر', 'en' => 'Short description'],
                'description' => ['ar' => 'وصف الدورة', 'en' => 'Course description'],
                'thumbnail_url' => null,
                'hero_url' => null,
                'status' => 'published',
                'is_featured' => $position <= 3,
                'published_at' => now()->subDays($position),
            ]);

            CourseVideo::query()->create([
                'course_id' => $course->id,
                'title' => ['ar' => "فيديو الدورة {$position}", 'en' => "Course {$position} video"],
                'lesson_label' => ['ar' => 'الدرس الأول', 'en' => 'Lesson one'],
                'source_path' => "courses/{$course->id}/videos/fixture.mp4",
                'duration_seconds' => 100,
                'sort_order' => 1,
                'status' => 'ready',
            ]);

            $courses[] = $course;
        }

        $subscribedCourse = $courses[0];
        $subscribedVideo = $subscribedCourse->videos()->firstOrFail();

        $subscription = CourseSubscription::query()->create([
            'user_id' => $subscriber->id,
            'course_id' => $subscribedCourse->id,
            'source' => 'qr',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $this->createFixtureQrCode(
            $courses[1],
            'ELDER-PHYSICS-2026-GROUP',
            180,
            $activeUser,
        );

        $this->createFixtureQrCode(
            $subscribedCourse,
            'ELDER-MATH-2026-365',
            365,
            $activeUser,
        );

        return [
            'active_user' => $activeUser,
            'subscriber' => $subscriber,
            'suspended_user' => $suspendedUser,
            'courses' => $courses,
            'subscribed_course' => $subscribedCourse,
            'subscribed_video' => $subscribedVideo,
            'subscription' => $subscription,
        ];
    }

    protected function createFixtureUser(string $phone, string $status = 'active'): User
    {
        return User::query()->create([
            'full_name' => "Fixture {$phone}",
            'phone' => $phone,
            'email' => 'fixture-'.ltrim($phone, '+').'@example.test',
            'password' => 'Password123!',
            'phone_verified_at' => now(),
            'status' => $status,
            'is_admin' => false,
            'suspended_at' => $status === 'suspended' ? now() : null,
            'suspension_reason' => $status === 'suspended' ? 'Fixture suspension' : null,
        ]);
    }

    protected function createFixtureQrCode(
        Course $course,
        string $rawCode,
        int $durationDays,
        User $creator,
    ): SubscriptionQrCode {
        return SubscriptionQrCode::query()->create([
            'course_id' => $course->id,
            'code_hash' => hash('sha256', $rawCode),
            'code_hint' => substr($rawCode, 0, 6).'****'.substr($rawCode, -4),
            'label' => $rawCode,
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addYear(),
            'max_redemptions' => 1,
            'redemptions_count' => 0,
            'subscription_duration_days' => $durationDays,
            'status' => 'active',
            'created_by' => $creator->id,
        ]);
    }
}
