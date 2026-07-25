<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResources;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\Subject;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller {
 use ApiResponse;
 public function home(Request $r){
  $years=AcademicYear::where('is_active',true)->withCount(['subjects'=>fn($q)=>$q->where('is_active',true)])->orderBy('sort_order')->get()->map(fn($x)=>CatalogResources::year($x));
  $limit=min(20,max(1,$r->integer('featured_limit',10)));
  $courses=Course::where('status','published')->where('is_featured',true)->withCount(['videos'=>fn($q)=>$q->where('status','ready'),'files'])->withSum(['videos as total_duration_seconds'=>fn($q)=>$q->where('status','ready')],'duration_seconds')->latest('published_at')->limit($limit)->get()->map(fn($c)=>CatalogResources::course($c,$r->user()));
  return $this->success(['user'=>['id'=>$r->user()->id,'first_name'=>str($r->user()->full_name)->before(' ')->toString(),'avatar_url'=>$r->user()->avatar_path?asset('storage/'.$r->user()->avatar_path):null],'academic_years'=>$years,'featured_courses'=>$courses,'unread_notifications_count'=>$r->user()->unreadNotifications()->count()]);
 }
 public function academicYears(Request $r){
  $q=AcademicYear::where('is_active',true)->withCount(['subjects'=>fn($s)=>$s->where('is_active',true)])->orderBy('sort_order');
  if($r->filled('q'))$q->whereJsonContains('title->'.app()->getLocale(),$r->string('q'));
  $p=$q->paginate(min(50,$r->integer('per_page',15)));$p->setCollection($p->getCollection()->map(fn($x)=>CatalogResources::year($x)));return $this->success($p);
 }
 public function subjects(Request $r,AcademicYear $academicYear){
  abort_unless($academicYear->is_active,404);$q=$academicYear->subjects()->where('is_active',true)->withCount(['courses'=>fn($c)=>$c->where('status','published')])->orderBy('sort_order');
  if($r->filled('q'))$q->whereJsonContains('title->'.app()->getLocale(),$r->string('q'));
  $p=$q->paginate(min(50,$r->integer('per_page',15)));$p->setCollection($p->getCollection()->map(fn($x)=>CatalogResources::subject($x)));
  return $this->success(['academic_year'=>CatalogResources::year($academicYear),'subjects'=>$p->items()],meta:['current_page'=>$p->currentPage(),'last_page'=>$p->lastPage(),'per_page'=>$p->perPage(),'total'=>$p->total(),'from'=>$p->firstItem(),'to'=>$p->lastItem(),'has_more'=>$p->hasMorePages()]);
 }
 public function subjectCourses(Request $r,Subject $subject){
  abort_unless($subject->is_active,404);$p=$this->courseQuery($r)->where('subject_id',$subject->id)->paginate(min(50,$r->integer('per_page',15)));
  $p->setCollection($p->getCollection()->map(fn($c)=>CatalogResources::course($c,$r->user())));
  return $this->success(['subject'=>CatalogResources::subject($subject),'courses'=>$p->items()],meta:['current_page'=>$p->currentPage(),'last_page'=>$p->lastPage(),'per_page'=>$p->perPage(),'total'=>$p->total(),'from'=>$p->firstItem(),'to'=>$p->lastItem(),'has_more'=>$p->hasMorePages()]);
 }
 public function courses(Request $r){$p=$this->courseQuery($r)->paginate(min(50,$r->integer('per_page',15)));$p->setCollection($p->getCollection()->map(fn($c)=>CatalogResources::course($c,$r->user())));return $this->success($p);}
 public function course(Request $r,Course $course){
  abort_unless($course->status==='published',404);$course->load(['subject.academicYear','videos'=>fn($q)=>$q->where('is_preview',true)->where('status','ready')->orderBy('sort_order')])->loadCount(['videos'=>fn($q)=>$q->where('status','ready'),'files'])->loadSum(['videos as total_duration_seconds'=>fn($q)=>$q->where('status','ready')],'duration_seconds');
  $sub=$course->subscriptions()->where('user_id',$r->user()->id)->latest()->first();$progress=CatalogResources::courseProgress($course,$r->user());
  return $this->success(array_merge(CatalogResources::course($course,$r->user()),['description'=>$course->translated('description'),'hero_url'=>$course->hero_path?asset('storage/'.$course->hero_path):null,'subject'=>CatalogResources::subject($course->subject),'academic_year'=>CatalogResources::year($course->subject->academicYear),'subscription'=>CatalogResources::subscription($sub,$progress),'first_playable_video_id'=>$course->videos()->where('status','ready')->where(fn($q)=>$sub?->isActive()?$q:$q->where('is_preview',true))->orderBy('sort_order')->value('id'),'preview_videos'=>$course->videos->map(fn($v)=>CatalogResources::video($v,$r->user(),false)),'can_view_full_content'=>$sub?->isActive()??false]));
 }
 public function myCourses(Request $r){return $this->subscriptionList($r);}
 public function subscriptions(Request $r){return $this->subscriptionList($r);}
 public function subscription(Request $r,CourseSubscription $subscription){abort_unless($subscription->user_id===$r->user()->id,404);$subscription->load('course');return $this->success(array_merge(CatalogResources::subscription($subscription,CatalogResources::courseProgress($subscription->course,$r->user())),['course'=>CatalogResources::course($subscription->course,$r->user()),'activated_by'=>$subscription->source,'redemption'=>null,'created_at'=>$subscription->created_at->toIso8601String(),'updated_at'=>$subscription->updated_at->toIso8601String()]));}
 private function subscriptionList(Request $r){$q=$r->user()->subscriptions()->with('course')->latest();if($r->filled('status'))$q->where('status',$r->string('status'));if($r->filled('source'))$q->where('source',$r->string('source'));$p=$q->paginate(min(50,$r->integer('per_page',15)));$p->setCollection($p->getCollection()->map(fn($s)=>array_merge(CatalogResources::subscription($s,CatalogResources::courseProgress($s->course,$r->user())),['course'=>CatalogResources::course($s->course,$r->user())])));return $this->success($p);}
 private function courseQuery(Request $r){$q=Course::where('status','published')->withCount(['videos'=>fn($v)=>$v->where('status','ready'),'files'])->withSum(['videos as total_duration_seconds'=>fn($v)=>$v->where('status','ready')],'duration_seconds');if($r->filled('subject_id'))$q->where('subject_id',$r->integer('subject_id'));if($r->filled('academic_year_id'))$q->whereHas('subject',fn($s)=>$s->where('academic_year_id',$r->integer('academic_year_id')));if($r->has('featured'))$q->where('is_featured',$r->boolean('featured'));if($r->filled('q'))$q->whereJsonContains('title->'.app()->getLocale(),$r->string('q'));return $q->latest('published_at');}
}
