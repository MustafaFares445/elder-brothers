<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseSubscription;
use App\Models\CourseVideo;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RealCourseMediaSeeder extends Seeder
{
    private const COURSE_SLUG = 'real-private-media-course';

    private const STUDENT_PHONE = '+963900000100';

    private const STUDENT_EMAIL = 'media.student@elder.local';

    public function run(): void
    {
        $disk = Storage::disk(config('filesystems.course_media', 'local'));
        $videoContents = $this->decodeAsset('sample-course-video.mp4.base64');
        $pdfContents = $this->decodeAsset('sample-course-document.pdf.base64');

        $course = $this->seedCourse();
        $this->seedCourseVideos($disk, $course, $videoContents);
        $this->seedCourseFiles($disk, $course, $pdfContents);
        $this->seedSubscribedStudent($course);

        $this->repairMissingVideoFiles($disk, $videoContents);
        $this->repairMissingPdfFiles($disk, $pdfContents);

        $this->command?->info('تم إنشاء ملفات MP4 وPDF فعلية داخل التخزين الخاص وإصلاح مسارات الوسائط المفقودة.');
        $this->command?->line('طالب الاختبار: '.self::STUDENT_PHONE.' / Password123!');
    }

    private function seedCourse(): Course
    {
        $academicYear = AcademicYear::query()->updateOrCreate(
            ['sort_order' => 9000],
            [
                'title' => ['ar' => 'السنة التجريبية للوسائط'],
                'subtitle' => ['ar' => 'بيانات فعلية لاختبار الفيديو وملفات PDF'],
                'icon' => 'video_library',
                'is_active' => true,
            ],
        );

        $subject = Subject::query()->updateOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'sort_order' => 9000,
            ],
            [
                'title' => ['ar' => 'اختبار الوسائط الخاصة'],
                'image_url' => null,
                'is_active' => true,
            ],
        );

        return Course::query()->updateOrCreate(
            ['slug' => self::COURSE_SLUG],
            [
                'subject_id' => $subject->id,
                'title' => ['ar' => 'دورة اختبار الفيديو وملفات PDF'],
                'short_description' => ['ar' => 'دورة تحتوي على ملفات MP4 وPDF فعلية مخزنة محليًا بشكل خاص.'],
                'description' => ['ar' => 'تستخدم هذه الدورة للتحقق من روابط التشغيل والبث والتنزيل الموقعة دون الاعتماد على مسارات وهمية.'],
                'thumbnail_url' => null,
                'hero_url' => null,
                'status' => 'published',
                'is_featured' => true,
                'published_at' => now(),
            ],
        );
    }

    private function seedCourseVideos(FilesystemAdapter $disk, Course $course, string $contents): void
    {
        $definitions = [
            [
                'sort_order' => 1,
                'title' => 'المحاضرة الأولى: اختبار البث الخاص',
                'lesson_label' => 'المحاضرة الأولى',
                'filename' => 'private-streaming-introduction.mp4',
            ],
            [
                'sort_order' => 2,
                'title' => 'المحاضرة الثانية: اختبار تنزيل الفيديو',
                'lesson_label' => 'المحاضرة الثانية',
                'filename' => 'private-video-download.mp4',
            ],
        ];

        foreach ($definitions as $definition) {
            $path = "courses/{$course->id}/videos/{$definition['filename']}";
            $disk->put($path, $contents);

            CourseVideo::query()->updateOrCreate(
                [
                    'course_id' => $course->id,
                    'sort_order' => $definition['sort_order'],
                ],
                [
                    'title' => ['ar' => $definition['title']],
                    'lesson_label' => ['ar' => $definition['lesson_label']],
                    'thumbnail_url' => null,
                    'source_path' => $path,
                    'hls_manifest_path' => null,
                    'duration_seconds' => 2,
                    'status' => 'ready',
                    'is_preview' => false,
                    'is_downloadable' => true,
                ],
            );
        }
    }

    private function seedCourseFiles(FilesystemAdapter $disk, Course $course, string $contents): void
    {
        $definitions = [
            [
                'sort_order' => 1,
                'title' => 'ملخص اختبار الوسائط الخاصة',
                'filename' => 'private-course-notes.pdf',
                'original_name' => 'ملخص-الدورة.pdf',
            ],
            [
                'sort_order' => 2,
                'title' => 'تمارين اختبار تنزيل PDF',
                'filename' => 'private-course-exercises.pdf',
                'original_name' => 'تمارين-الدورة.pdf',
            ],
        ];

        foreach ($definitions as $definition) {
            $path = "courses/{$course->id}/pdfs/{$definition['filename']}";
            $disk->put($path, $contents);

            CourseFile::query()->updateOrCreate(
                [
                    'course_id' => $course->id,
                    'sort_order' => $definition['sort_order'],
                ],
                [
                    'title' => ['ar' => $definition['title']],
                    'file_path' => $path,
                    'external_url' => null,
                    'original_name' => $definition['original_name'],
                    'mime_type' => 'application/pdf',
                    'extension' => 'pdf',
                    'size_bytes' => strlen($contents),
                    'is_downloadable' => true,
                ],
            );
        }
    }

    private function seedSubscribedStudent(Course $course): void
    {
        $student = User::query()
            ->where('phone', self::STUDENT_PHONE)
            ->orWhere('email', self::STUDENT_EMAIL)
            ->first() ?? new User();

        $student->fill([
            'full_name' => 'طالب اختبار الوسائط',
            'phone' => self::STUDENT_PHONE,
            'email' => self::STUDENT_EMAIL,
            'password' => 'Password123!',
            'phone_verified_at' => $student->phone_verified_at ?? now(),
            'status' => 'active',
            'is_admin' => false,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
        $student->save();

        CourseSubscription::query()->updateOrCreate(
            [
                'user_id' => $student->id,
                'course_id' => $course->id,
            ],
            [
                'source' => 'qr',
                'starts_at' => now(),
                'expires_at' => now()->addYear(),
                'revoked_at' => null,
                'status' => 'active',
            ],
        );
    }

    private function repairMissingVideoFiles(FilesystemAdapter $disk, string $contents): void
    {
        CourseVideo::query()
            ->orderBy('id')
            ->chunkById(100, function ($videos) use ($disk, $contents): void {
                foreach ($videos as $video) {
                    $path = $video->source_path;
                    $requiresLocalPath = blank($path)
                        || Str::startsWith((string) $path, ['http://', 'https://'])
                        || strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)) !== 'mp4';

                    if ($requiresLocalPath) {
                        $path = "courses/{$video->course_id}/videos/seeded-video-{$video->id}.mp4";
                    }

                    if ($disk->exists($path)) {
                        continue;
                    }

                    $disk->put($path, $contents);
                    $video->forceFill([
                        'source_path' => $path,
                        'hls_manifest_path' => null,
                        'duration_seconds' => 2,
                        'status' => 'ready',
                        'is_preview' => false,
                        'is_downloadable' => true,
                    ])->save();
                }
            });
    }

    private function repairMissingPdfFiles(FilesystemAdapter $disk, string $contents): void
    {
        CourseFile::query()
            ->orderBy('id')
            ->chunkById(100, function ($files) use ($disk, $contents): void {
                foreach ($files as $file) {
                    $path = $file->file_path;
                    $requiresLocalPath = blank($path)
                        || Str::startsWith((string) $path, ['http://', 'https://'])
                        || strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)) !== 'pdf';

                    if ($requiresLocalPath) {
                        $path = "courses/{$file->course_id}/pdfs/seeded-file-{$file->id}.pdf";
                    }

                    if (! $disk->exists($path)) {
                        $disk->put($path, $contents);
                    }

                    $file->forceFill([
                        'file_path' => $path,
                        'external_url' => null,
                        'original_name' => str_ends_with(strtolower($file->original_name), '.pdf')
                            ? $file->original_name
                            : "ملف-الدورة-{$file->id}.pdf",
                        'mime_type' => 'application/pdf',
                        'extension' => 'pdf',
                        'size_bytes' => strlen($contents),
                        'is_downloadable' => true,
                    ])->save();
                }
            });
    }

    private function decodeAsset(string $filename): string
    {
        $encoded = file_get_contents(database_path("seeders/assets/{$filename}"));

        if ($encoded === false) {
            throw new RuntimeException("Unable to read seeded media asset: {$filename}");
        }

        $contents = base64_decode(preg_replace('/\s+/', '', $encoded) ?: '', true);

        if ($contents === false || $contents === '') {
            throw new RuntimeException("Invalid seeded media asset: {$filename}");
        }

        return $contents;
    }
}
