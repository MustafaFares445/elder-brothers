<?php

namespace Database\Seeders;

use App\Models\{AcademicYear, ContentPage, Course, CourseFile, CourseSubscription, CourseVideo, QrRedemption, Subject, SubscriptionQrCode, SupportRequest, User, UserDevice, UserPreference, VideoProgress};
use App\Notifications\AdminBroadcastNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ArabicDemoSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    public function run(): void
    {
        $this->call(PlatformSettingSeeder::class);
        $admin = $this->admin();
        $students = $this->students();
        $courses = $this->content();
        $subscriptions = $this->subscriptions($students, $courses);
        $this->progress($subscriptions);
        $this->qr($students, $courses, $admin);
        $this->support($students);
        $this->notifications($students, $courses);
        $this->pages();

        $this->command?->info('تم إنشاء البيانات العربية التجريبية كاملة.');
        $this->command?->line('كلمة المرور: '.self::PASSWORD);
        $this->command?->line('مدير البيئة المحلية: +963900000000');
    }

    private function admin(): ?User
    {
        if (filled(env('ADMIN_PHONE')) || (! app()->environment('local', 'testing') && ! env('SEED_DEMO_ADMIN', false))) {
            return null;
        }

        return User::query()->updateOrCreate(['phone' => '+963900000000'], [
            'full_name' => 'مدير النظام التجريبي', 'email' => 'admin@elder.local',
            'password' => self::PASSWORD, 'phone_verified_at' => now()->subMonths(6),
            'status' => 'active', 'is_admin' => true, 'last_login_at' => now()->subHour(),
            'suspended_at' => null, 'suspension_reason' => null,
        ]);
    }

    /** @return Collection<int, User> */
    private function students(): Collection
    {
        $names = ['أحمد محمد البتار','سارة خالد العلي','محمود علي حسن','نور حسن إبراهيم','ليان أحمد صالح','عمر يوسف محمود','ريم عبد الرحمن','كريم سامر خليل','هبة وائل منصور','يزن فادي أحمد','ميساء رامي درويش','جود محمد السيد'];

        return collect($names)->map(function (string $name, int $i): User {
            $n = $i + 1;
            $suspended = in_array($n, [3, 11], true);
            $verified = $n !== 4;
            $user = User::query()->updateOrCreate(['phone' => '+9639000000'.str_pad((string) $n, 2, '0', STR_PAD_LEFT)], [
                'full_name' => $name, 'email' => "student{$n}@example.com", 'password' => self::PASSWORD,
                'phone_verified_at' => $verified ? now()->subDays(70 - $n) : null,
                'status' => $suspended ? 'suspended' : 'active', 'is_admin' => false,
                'last_login_at' => $verified ? now()->subHours($n) : null,
                'suspended_at' => $suspended ? now()->subDays($n) : null,
                'suspension_reason' => $suspended ? 'تم إيقاف الحساب مؤقتًا بقرار الإدارة.' : null,
            ]);
            UserPreference::query()->updateOrCreate(['user_id' => $user->id], [
                'locale' => 'ar', 'smart_notifications' => $n % 4 !== 0,
                'download_quality' => ['auto', 'hd', 'sd'][$i % 3],
            ]);
            if ($verified) {
                UserDevice::query()->updateOrCreate(['user_id' => $user->id, 'device_id' => "جهاز-{$n}"], [
                    'fcm_token' => "رمز-إشعار-{$n}", 'platform' => $n % 3 === 0 ? 'ios' : 'android',
                    'app_version' => "1.0.{$n}", 'notifications_enabled' => $n % 4 !== 0,
                    'last_seen_at' => now()->subMinutes($n * 15),
                ]);
            }
            return $user;
        })->values();
    }

    /** @return Collection<int, Course> */
    private function content(): Collection
    {
        $yearData = [
            ['السنة الأولى','المواد التأسيسية وبناء المهارات الأساسية','school'],
            ['السنة الثانية','التوسع في المفاهيم والتطبيقات العملية','menu_book'],
            ['السنة الثالثة','التدريب المتقدم والاستعداد للامتحانات','history_edu'],
            ['السنة الرابعة','المراجعة الشاملة والتحضير النهائي','workspace_premium'],
        ];
        $subjects = [
            ['الرياضيات','شرح القواعد الرياضية والتدريب على حل المسائل.'],
            ['الفيزياء','تبسيط المفاهيم الفيزيائية والمسائل الامتحانية.'],
            ['الكيمياء','فهم العناصر والمركبات والتفاعلات الكيميائية.'],
            ['الأحياء','دراسة الخلية وأجهزة الجسم والوراثة.'],
            ['اللغة العربية','تقوية النحو والصرف والبلاغة والكتابة.'],
            ['اللغة الإنجليزية','تطوير القواعد والمفردات والقراءة.'],
        ];
        $courses = collect();
        $index = 0;

        foreach ($yearData as $yi => [$yearTitle, $subtitle, $icon]) {
            $year = AcademicYear::query()->updateOrCreate(['sort_order' => $yi + 1], [
                'title' => $this->t($yearTitle), 'subtitle' => $this->t($subtitle),
                'icon' => $icon, 'is_active' => true,
            ]);
            foreach ($subjects as $si => [$subjectName, $focus]) {
                $subject = Subject::query()->updateOrCreate(['academic_year_id' => $year->id, 'sort_order' => $si + 1], [
                    'title' => $this->t($subjectName), 'image_url' => null, 'is_active' => true,
                ]);
                $status = match ($index % 8) { 6 => 'draft', 7 => 'archived', default => 'published' };
                $title = "المنهاج الكامل في {$subjectName} - {$yearTitle}";
                $short = "{$focus} تتضمن الدورة شرحًا مبسطًا وأمثلة محلولة وتدريبات متدرجة.";
                $course = Course::query()->updateOrCreate(['slug' => 'arabic-course-'.($yi + 1).'-'.($si + 1)], [
                    'subject_id' => $subject->id, 'title' => $this->t($title),
                    'short_description' => $this->t($short),
                    'description' => $this->t("{$short} يشمل المحتوى محاضرات منظمة وملفات مراجعة شاملة."),
                    'thumbnail_url' => "courses/{$year->id}-{$subject->id}/images/thumbnail.jpg",
                    'hero_url' => "courses/{$year->id}-{$subject->id}/images/hero.jpg",
                    'status' => $status, 'is_featured' => $status === 'published' && $si < 2,
                    'published_at' => $status === 'published' ? now()->subDays(75 - $index) : null,
                ]);
                foreach (['مقدمة وخطة الدراسة','شرح المفاهيم الأساسية','حل أمثلة وتطبيقات','مراجعة شاملة وأسئلة امتحانية'] as $vi => $topic) {
                    $v = $vi + 1;
                    CourseVideo::query()->updateOrCreate(['course_id' => $course->id, 'sort_order' => $v], [
                        'title' => $this->t("المحاضرة {$v}: {$topic}"), 'lesson_label' => $this->t("الدرس {$v} في {$subjectName}"),
                        'thumbnail_url' => "courses/{$course->id}/video-thumbnails/lecture-{$v}.jpg",
                        'source_path' => "courses/{$course->id}/videos/lecture-{$v}.mp4", 'hls_manifest_path' => null,
                        'duration_seconds' => 1500 + ($v * 360), 'is_preview' => $v === 1,
                        'is_downloadable' => $v > 1,
                        'status' => $status === 'draft' ? ($v === 4 ? 'failed' : 'processing') : 'ready',
                    ]);
                }
                foreach ([['ملخص الدورة', 2097152], ['أسئلة وتدريبات محلولة', 3145728]] as $fi => [$fileTitle, $size]) {
                    $f = $fi + 1;
                    CourseFile::query()->updateOrCreate(['course_id' => $course->id, 'sort_order' => $f], [
                        'title' => $this->t("{$fileTitle} - {$subjectName}"),
                        'file_path' => "courses/{$course->id}/pdfs/file-{$f}.pdf", 'external_url' => null,
                        'original_name' => "ملف-{$subjectName}-{$f}.pdf", 'mime_type' => 'application/pdf',
                        'extension' => 'pdf', 'size_bytes' => $size, 'is_downloadable' => true,
                    ]);
                }
                $courses->push($course);
                $index++;
            }
        }
        return $courses;
    }

    /** @return Collection<int, CourseSubscription> */
    private function subscriptions(Collection $students, Collection $courses): Collection
    {
        $published = $courses->where('status', 'published')->values();
        $all = collect();
        foreach ($students->take(10) as $ui => $student) {
            foreach (range(0, 3) as $slot) {
                $course = $published[($ui * 2 + $slot) % $published->count()];
                $status = match ($slot) { 0, 1 => 'active', 2 => 'expired', default => 'revoked' };
                $subscription = CourseSubscription::query()->updateOrCreate(['user_id' => $student->id, 'course_id' => $course->id], [
                    'source' => $slot % 2 ? 'qr' : 'admin', 'starts_at' => now()->subDays(90 + $ui * 3),
                    'expires_at' => $status === 'active' ? now()->addDays(120 + $slot * 30) : ($status === 'expired' ? now()->subDays(15 + $ui) : now()->addDays(90)),
                    'revoked_at' => $status === 'revoked' ? now()->subDays(5 + $ui) : null, 'status' => $status,
                ]);
                $subscription->setRelation('course', $course);
                $all->push($subscription);
            }
        }
        return $all;
    }

    private function progress(Collection $subscriptions): void
    {
        $percentages = [100, 70, 25];
        foreach ($subscriptions->whereIn('status', ['active', 'expired']) as $si => $subscription) {
            foreach ($subscription->course->videos()->where('status', 'ready')->orderBy('sort_order')->limit(3)->get() as $vi => $video) {
                $percentage = $percentages[($si + $vi) % 3];
                $watched = (int) floor($video->duration_seconds * $percentage / 100);
                VideoProgress::query()->updateOrCreate(['user_id' => $subscription->user_id, 'course_video_id' => $video->id], [
                    'watched_seconds' => $watched, 'last_position_seconds' => $percentage === 100 ? $video->duration_seconds : max(0, $watched - 15),
                    'completed_at' => $percentage === 100 ? now()->subDays(($si % 20) + 1) : null,
                    'last_watched_at' => now()->subHours(($si + $vi) % 72),
                ]);
            }
        }
    }

    private function qr(Collection $students, Collection $courses, ?User $admin): void
    {
        $published = $courses->where('status', 'published')->values();
        $defs = [
            ['ELDER-AR-INDIVIDUAL-001','اشتراك فردي في دورة الرياضيات',0,365,5,'active'],
            ['ELDER-AR-GROUP-002','اشتراك مجموعة طلاب في دورة الفيزياء',1,180,50,'active'],
            ['ELDER-AR-UNLIMITED-003','اشتراك غير محدد المدة في دورة الكيمياء',2,null,null,'active'],
            ['ELDER-AR-DISABLED-004','رمز متوقف بقرار الإدارة',3,90,10,'disabled'],
            ['ELDER-AR-EXPIRED-005','رمز منتهي الصلاحية',4,120,10,'expired'],
            ['ELDER-AR-EXHAUSTED-006','رمز استُخدم حتى الحد الأقصى',5,60,2,'exhausted'],
            ['ELDER-AR-SINGLE-007','رمز فردي جاهز للاستخدام',6,365,1,'active'],
            ['ELDER-AR-CAMPAIGN-008','رمز حملة الاشتراكات التعليمية',7,240,20,'active'],
        ];
        $codes = collect();
        foreach ($defs as [$raw, $label, $ci, $duration, $max, $status]) {
            $codes->push(SubscriptionQrCode::query()->updateOrCreate(['code_hash' => hash('sha256', $raw)], [
                'course_id' => $published[$ci]->id, 'code_hint' => substr($raw, 0, 6).str_repeat('*', max(1, strlen($raw) - 10)).substr($raw, -4),
                'label' => $label, 'starts_at' => now()->subDays(30),
                'expires_at' => $status === 'expired' ? now()->subDay() : now()->addYear(),
                'max_redemptions' => $max, 'redemptions_count' => 0,
                'subscription_duration_days' => $duration, 'status' => $status,
                'created_by' => $admin?->id ?? $students->first()->id,
            ]));
        }
        foreach ([[0,0],[0,1],[1,2],[1,3],[2,4],[5,5],[5,6]] as [$ci, $ui]) {
            $code = $codes[$ci]; $student = $students[$ui];
            $subscription = CourseSubscription::query()->updateOrCreate(['user_id' => $student->id, 'course_id' => $code->course_id], [
                'source' => 'qr', 'starts_at' => now()->subDays(20 - $ui),
                'expires_at' => $code->subscription_duration_days ? now()->addDays($code->subscription_duration_days) : null,
                'revoked_at' => null, 'status' => 'active',
            ]);
            QrRedemption::query()->updateOrCreate(['subscription_qr_code_id' => $code->id, 'user_id' => $student->id], [
                'course_subscription_id' => $subscription->id, 'redeemed_at' => now()->subDays(10 - min($ui, 9)),
                'ip_address' => '192.168.1.'.($ui + 10), 'device_id' => 'جهاز-'.($ui + 1),
            ]);
        }
        foreach ($codes as $code) {
            $count = $code->redemptions()->count();
            $code->update(['redemptions_count' => $count, 'status' => $code->status === 'active' && $code->max_redemptions !== null && $count >= $code->max_redemptions ? 'exhausted' : $code->status]);
        }
        foreach ($defs as [$raw, $label]) { $this->command?->line("{$label}: {$raw}"); }
    }

    private function support(Collection $students): void
    {
        $rows = [
            ['تعذر تشغيل المحاضرة الثانية','يتوقف الفيديو بعد ثوانٍ من بدء التشغيل.','technical','open'],
            ['لم يظهر الاشتراك بعد مسح الرمز','تم مسح الرمز ولكن الدورة لم تظهر.','subscription','in_progress'],
            ['خطأ في ملف المراجعة','يظهر ملف المراجعة فارغًا.','content','resolved'],
            ['أرغب في تعديل رقم الهاتف','أحتاج إلى تغيير رقم الهاتف.','account','closed'],
            ['اقتراح إضافة اختبار قصير','أقترح إضافة اختبار بعد كل محاضرة.','other','open'],
            ['الصوت منخفض في المحاضرة','صوت المدرس منخفض في المحاضرة الثالثة.','technical','in_progress'],
            ['مدة الاشتراك غير صحيحة','تاريخ انتهاء الاشتراك أقصر من المدة المكتوبة.','subscription','resolved'],
            ['وجود خطأ لغوي في الدرس','يوجد خطأ إملائي في عنوان الدرس.','content','closed'],
            ['نسيت كلمة المرور','لم يصلني رمز استعادة كلمة المرور.','account','open'],
            ['طلب إضافة مادة جديدة','أرجو إضافة محتوى إضافي لمادة اللغة العربية.','other','in_progress'],
            ['التطبيق يغلق عند التنزيل','يغلق التطبيق عند تنزيل ملف كبير.','technical','resolved'],
            ['لا تظهر الإشعارات','لا تصلني إشعارات الدروس الجديدة.','account','open'],
        ];
        foreach ($rows as $i => [$subject, $message, $category, $status]) {
            SupportRequest::query()->updateOrCreate(['reference' => 'دعم-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT)], [
                'user_id' => $students[$i % $students->count()]->id, 'subject' => $subject, 'message' => $message,
                'category' => $category, 'attachment_path' => null,
                'device_info' => ['اسم الجهاز' => $i % 2 ? 'هاتف آيفون' : 'هاتف أندرويد', 'إصدار التطبيق' => '1.0.'.($i + 1), 'لغة التطبيق' => 'العربية'],
                'status' => $status,
            ]);
        }
    }

    private function notifications(Collection $students, Collection $courses): void
    {
        $published = $courses->where('status', 'published')->values();
        $templates = [
            ['course_published','تم نشر دورة تعليمية جديدة','أصبحت دورة جديدة متاحة الآن.','course'],
            ['subscription_reminder','تذكير بانتهاء الاشتراك','تبقى وقت محدود على انتهاء أحد اشتراكاتك.','subscription'],
            ['study_reminder','حان وقت متابعة دروسك','تابع من آخر نقطة وصلت إليها اليوم.','course'],
        ];
        foreach ($students->take(10) as $ui => $student) {
            foreach ($templates as $ti => [$type, $title, $body, $actionType]) {
                $course = $published[($ui + $ti) % $published->count()];
                $hash = md5("arabic-notification-{$student->id}-{$ti}");
                $id = substr($hash,0,8).'-'.substr($hash,8,4).'-'.substr($hash,12,4).'-'.substr($hash,16,4).'-'.substr($hash,20,12);
                $created = now()->subHours($ui * 3 + $ti + 1);
                DB::table('notifications')->updateOrInsert(['id' => $id], [
                    'type' => AdminBroadcastNotification::class, 'notifiable_type' => User::class, 'notifiable_id' => $student->id,
                    'data' => json_encode(['type'=>$type,'title_ar'=>$title,'title_en'=>$title,'body_ar'=>$body,'body_en'=>$body,'image_url'=>null,'action_type'=>$actionType,'action_id'=>$course->id,'action_url'=>null], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'read_at' => ($ui + $ti) % 3 === 0 ? $created->copy()->addMinutes(20) : null,
                    'created_at' => $created, 'updated_at' => $created,
                ]);
            }
        }
    }

    private function pages(): void
    {
        foreach ([
            ['privacy-policy','سياسة الخصوصية','نحترم خصوصية المستخدم ونلتزم بحماية بياناته الشخصية واستخدامها لتشغيل الخدمة التعليمية.'],
            ['terms','شروط الاستخدام','يُسمح باستخدام المحتوى التعليمي للاستخدام الشخصي فقط ويمنع إعادة نشره أو مشاركة الحساب.'],
            ['help','المساعدة والدعم','يمكنك التواصل مع فريق الدعم من صفحة الدعم داخل التطبيق مع وصف المشكلة بوضوح.'],
        ] as [$slug, $title, $content]) {
            ContentPage::query()->updateOrCreate(['slug' => $slug], ['title' => $this->t($title), 'content' => $this->t($content), 'is_active' => true]);
        }
    }

    /** @return array{ar:string,en:string} */
    private function t(string $value): array { return ['ar' => $value, 'en' => $value]; }
}
