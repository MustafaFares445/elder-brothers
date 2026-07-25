<?php
namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ContentPage;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseSubscription;
use App\Models\CourseVideo;
use App\Models\Subject;
use App\Models\SubscriptionQrCode;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\VideoProgress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
 public function run(): void
 {
  $students=[
   ['full_name'=>'أحمد محمد البتار','phone'=>'+963900000001','email'=>'ahmed@example.com','status'=>'active','verified'=>true],
   ['full_name'=>'سارة خالد','phone'=>'+963900000002','email'=>'sara@example.com','status'=>'active','verified'=>true],
   ['full_name'=>'محمود علي','phone'=>'+963900000003','email'=>'mahmoud@example.com','status'=>'suspended','verified'=>true],
   ['full_name'=>'نور حسن','phone'=>'+963900000004','email'=>'nour@example.com','status'=>'active','verified'=>false],
  ];
  $users=[];
  foreach($students as $row){$u=User::create(['full_name'=>$row['full_name'],'phone'=>$row['phone'],'email'=>$row['email'],'password'=>'Password123!','status'=>$row['status'],'phone_verified_at'=>$row['verified']?now():null]);UserPreference::create(['user_id'=>$u->id,'locale'=>'ar','smart_notifications'=>true,'download_quality'=>'auto']);$users[]=$u;}

  $yearData=[
   ['السنة الأولى','First Year','المواد الأساسية للمرحلة الأولى','Core first-year subjects','school'],
   ['السنة الثانية','Second Year','مواد متقدمة وتطبيقية','Advanced and applied subjects','menu_book'],
   ['السنة الثالثة','Third Year','التحضير المكثف للامتحانات','Intensive exam preparation','history_edu'],
   ['السنة الرابعة','Fourth Year','مرحلة التخصص والتخرج','Specialization and graduation','workspace_premium'],
  ];
  $years=[];foreach($yearData as $i=>$y){$years[]=AcademicYear::create(['title'=>['ar'=>$y[0],'en'=>$y[1]],'subtitle'=>['ar'=>$y[2],'en'=>$y[3]],'icon'=>$y[4],'sort_order'=>$i+1,'is_active'=>true]);}

  $subjectNames=[
   ['الرياضيات','Mathematics'],['الفيزياء','Physics'],['الكيمياء','Chemistry'],['الأحياء','Biology'],
   ['اللغة العربية','Arabic Language'],['اللغة الإنجليزية','English Language']
  ];
  $subjects=[];
  foreach($years as $year){foreach($subjectNames as $i=>$name){$subjects[]=Subject::create(['academic_year_id'=>$year->id,'title'=>['ar'=>$name[0],'en'=>$name[1]],'sort_order'=>$i+1,'is_active'=>true]);}}

  $courseDefinitions=[
   ['الرياضيات المتقدمة','Advanced Mathematics','شرح شامل للتفاضل والتكامل مع أمثلة امتحانية وتدريبات عملية.','A complete calculus course with exam examples and practical exercises.',true],
   ['الميكانيكا الكلاسيكية','Classical Mechanics','دراسة الحركة والقوى والطاقة من الأساس حتى المسائل المتقدمة.','Motion, forces, and energy from fundamentals to advanced problems.',true],
   ['الكيمياء العضوية','Organic Chemistry','فهم المركبات العضوية والتفاعلات الأساسية بطريقة مبسطة.','Understand organic compounds and core reactions in a clear way.',true],
   ['أساسيات الأحياء الخلوية','Cell Biology Fundamentals','تركيب الخلية ووظائفها والانقسام الخلوي والوراثة.','Cell structure, functions, division, and genetics.',false],
   ['البلاغة والنقد الأدبي','Rhetoric and Literary Criticism','تدريب تطبيقي على البلاغة وتحليل النصوص الأدبية.','Practical rhetoric and literary text analysis.',false],
   ['اللغة الإنجليزية الأكاديمية','Academic English','قواعد ومفردات وقراءة وكتابة موجهة للطلاب.','Grammar, vocabulary, reading, and writing for students.',false],
  ];
  $courses=[];
  foreach(array_slice($subjects,0,12) as $idx=>$subject){
   $def=$courseDefinitions[$idx%count($courseDefinitions)];
   $course=Course::create(['subject_id'=>$subject->id,'title'=>['ar'=>$def[0],'en'=>$def[1]],'description'=>['ar'=>$def[2],'en'=>$def[3]],'status'=>'published','is_featured'=>$def[4],'published_at'=>now()->subDays(30-$idx)]);
   $courses[]=$course;
   for($v=1;$v<=4;$v++){CourseVideo::create(['course_id'=>$course->id,'title'=>['ar'=>"المحاضرة {$v}: ".$def[0],'en'=>"Lecture {$v}: ".$def[1]],'lesson_label'=>['ar'=>"الدرس {$v}",'en'=>"Lesson {$v}"],'source_path'=>"courses/{$course->id}/videos/lecture-{$v}.mp4",'hls_manifest_path'=>"courses/{$course->id}/hls/lecture-{$v}/master.m3u8",'duration_seconds'=>2400+($v*300),'sort_order'=>$v,'is_preview'=>$v===1,'is_downloadable'=>$v>1,'status'=>'ready']);}
   for($f=1;$f<=2;$f++){CourseFile::create(['course_id'=>$course->id,'title'=>['ar'=>"ملف المراجعة {$f}",'en'=>"Review File {$f}"],'file_path'=>"courses/{$course->id}/files/review-{$f}.pdf",'original_name'=>"course-{$course->id}-review-{$f}.pdf",'mime_type'=>'application/pdf','extension'=>'pdf','size_bytes'=>(2+$f)*1024*1024,'sort_order'=>$f,'is_downloadable'=>true]);}
  }

  $subscription=CourseSubscription::create(['user_id'=>$users[0]->id,'course_id'=>$courses[0]->id,'source'=>'admin','starts_at'=>now()->subMonths(2),'expires_at'=>now()->addMonths(10),'status'=>'active']);
  CourseSubscription::create(['user_id'=>$users[0]->id,'course_id'=>$courses[1]->id,'source'=>'qr','starts_at'=>now()->subYear(),'expires_at'=>now()->subMonth(),'status'=>'expired']);
  CourseSubscription::create(['user_id'=>$users[1]->id,'course_id'=>$courses[2]->id,'source'=>'admin','starts_at'=>now()->subWeek(),'expires_at'=>now()->addYear(),'status'=>'active']);
  $firstVideo=$courses[0]->videos()->first();VideoProgress::create(['user_id'=>$users[0]->id,'course_video_id'=>$firstVideo->id,'watched_seconds'=>2100,'last_position_seconds'=>2000,'completed_at'=>now()->subDay(),'last_watched_at'=>now()->subDay()]);

  $codes=[
   ['ELDER-MATH-2026-001',$courses[0],365,1],
   ['ELDER-PHYSICS-2026-GROUP',$courses[1],180,50],
   ['ELDER-CHEMISTRY-LIFETIME',$courses[2],null,10],
  ];
  foreach($codes as $code){SubscriptionQrCode::create(['course_id'=>$code[1]->id,'code_hash'=>hash('sha256',$code[0]),'label'=>$code[0],'starts_at'=>now()->subDay(),'expires_at'=>now()->addYear(),'max_redemptions'=>$code[3],'redemptions_count'=>0,'subscription_duration_days'=>$code[2],'status'=>'active','created_by'=>$users[0]->id]);}

  foreach([
   ['privacy-policy','سياسة الخصوصية','Privacy Policy','نحترم خصوصيتك ونستخدم بياناتك لتقديم الخدمة التعليمية وحماية حسابك.','We respect your privacy and use your data to provide the learning service and protect your account.'],
   ['terms','شروط الاستخدام','Terms of Service','يجب استخدام المحتوى لأغراض تعليمية شخصية فقط وعدم إعادة نشره.','Content must be used only for personal educational purposes and must not be redistributed.'],
   ['help','المساعدة والدعم','Help and Support','يمكنك التواصل مع فريق الدعم من خلال نموذج طلب الدعم داخل التطبيق.','Contact the support team using the in-app support request form.'],
  ] as $page){ContentPage::create(['slug'=>$page[0],'title'=>['ar'=>$page[1],'en'=>$page[2]],'content'=>['ar'=>$page[3],'en'=>$page[4]],'is_active'=>true]);}
 }
}
