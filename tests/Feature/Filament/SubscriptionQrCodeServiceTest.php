<?php

namespace Tests\Feature\Filament;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Subject;
use App\Services\SubscriptionQrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionQrCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_only_a_hash_and_hint_for_a_raw_qr_code(): void
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

        $course = Course::create([
            'subject_id' => $subject->id,
            'slug' => 'mathematics',
            'title' => ['ar' => 'الرياضيات', 'en' => 'Mathematics'],
            'description' => ['ar' => 'وصف', 'en' => 'Description'],
            'status' => 'draft',
        ]);

        $rawCode = 'ELDER-TEST-QR-CODE';

        [$qrCode, $returnedCode] = app(SubscriptionQrCodeService::class)->create([
            'course_id' => $course->id,
            'label' => 'Test',
            'subscription_duration_days' => 30,
            'max_redemptions' => 1,
        ], $rawCode, null);

        $this->assertSame($rawCode, $returnedCode);
        $this->assertSame(hash('sha256', $rawCode), $qrCode->code_hash);
        $this->assertNotSame($rawCode, $qrCode->code_hint);
        $this->assertDatabaseMissing('subscription_qr_codes', ['code_hash' => $rawCode]);
    }
}
