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
    public function it_stores_the_full_code_with_custom_expiration_and_single_use_limit(): void
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
            'slug' => 'mathematics',
            'title' => ['ar' => 'الرياضيات'],
            'description' => ['ar' => 'وصف'],
            'status' => 'draft',
        ]);

        $rawCode = 'ELDER-TEST-QR-CODE';
        $expiresAt = now()->addDays(10)->startOfMinute();

        [$qrCode, $returnedCode] = app(SubscriptionQrCodeService::class)->create([
            'course_id' => $course->id,
            'label' => 'Test',
            'subscription_duration_days' => 30,
            'expires_at' => $expiresAt,
        ], $rawCode, null);

        $this->assertSame($rawCode, $returnedCode);
        $this->assertSame(hash('sha256', $rawCode), $qrCode->code_hash);
        $this->assertSame($rawCode, $qrCode->code_encrypted);
        $this->assertSame(1, $qrCode->max_redemptions);
        $this->assertTrue($qrCode->expires_at->equalTo($expiresAt));
        $this->assertStringContainsString(rawurlencode($rawCode), $qrCode->barcodeUrl());
        $this->assertDatabaseMissing('subscription_qr_codes', ['code_hash' => $rawCode]);
    }
}