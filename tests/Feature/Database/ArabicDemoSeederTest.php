<?php

namespace Tests\Feature\Database;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseVideo;
use App\Models\QrRedemption;
use App\Models\Subject;
use App\Models\SubscriptionQrCode;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\ArabicDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArabicDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_complete_arabic_demo_data(): void
    {
        $this->seed(ArabicDemoSeeder::class);

        $this->assertGreaterThanOrEqual(12, User::query()->where('is_admin', false)->count());
        $this->assertSame(4, AcademicYear::query()->count());
        $this->assertSame(24, Subject::query()->count());
        $this->assertSame(24, Course::query()->count());
        $this->assertSame(96, CourseVideo::query()->count());
        $this->assertSame(48, CourseFile::query()->count());
        $this->assertSame(8, SubscriptionQrCode::query()->count());
        $this->assertGreaterThanOrEqual(7, QrRedemption::query()->count());
        $this->assertSame(12, SupportRequest::query()->count());
        $this->assertGreaterThanOrEqual(30, DB::table('notifications')->count());

        $course = Course::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $course->title['ar']);
        $this->assertSame($course->title['ar'], $course->title['en']);
        $this->assertStringStartsWith('courses/', $course->thumbnail_url);
        $this->assertStringStartsWith('courses/', $course->hero_url);

        $this->assertSame(
            0,
            User::query()
                ->where('is_admin', false)
                ->whereDoesntHave('preferences', fn ($query) => $query->where('locale', 'ar'))
                ->count(),
        );
    }
}
