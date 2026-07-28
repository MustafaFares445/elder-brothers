<?php

namespace Tests\Feature\Filament;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseVideo;
use App\Models\Subject;
use App\Services\CoursePublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoursePublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_course_requires_ready_content_before_it_can_be_published(): void
    {
        $course = $this->course();

        $this->expectException(ValidationException::class);

        app(CoursePublishingService::class)->publish($course);
    }

    #[Test]
    public function a_complete_course_can_be_published(): void
    {
        $course = $this->course();

        CourseVideo::create([
            'course_id' => $course->id,
            'title' => ['ar' => 'المحاضرة الأولى', 'en' => 'Lecture One'],
            'source_path' => 'courses/test.mp4',
            'duration_seconds' => 120,
            'sort_order' => 1,
            'status' => 'ready',
        ]);

        $published = app(CoursePublishingService::class)->publish($course);

        $this->assertSame('published', $published->status);
        $this->assertNotNull($published->published_at);
    }

    private function course(): Course
    {
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

        return Course::create([
            'subject_id' => $subject->id,
            'slug' => 'complete-course',
            'title' => ['ar' => 'دورة مكتملة', 'en' => 'Complete Course'],
            'description' => ['ar' => 'وصف الدورة', 'en' => 'Course description'],
            'status' => 'draft',
        ]);
    }
}
