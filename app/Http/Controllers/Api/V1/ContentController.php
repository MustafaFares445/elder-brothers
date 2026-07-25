<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResources;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseVideo;
use App\Models\VideoProgress;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ContentController extends Controller {
 use ApiResponse;
 public function courseContent(Request $r,Course $course){
  abort_unless($course->status==='published',404);$sub=$this->subscription($r,$course);$full=$sub?->isActive()??false;$type=$r->string('type','all');
  $videos=in_array($type,['all','videos'])?$course->videos()->where('status','ready')->orderBy('sort_order')->get()->map(fn($v)=>CatalogResources::video($v,$r->user(),!$full&&!$v->is_preview)):collect();
  $files=in_array($type,['all','files'])?$course->files()->orderBy('sort_order')->get()->map(fn($f)=>CatalogResources::file($f,!$full)):collect();
  $access=$full?'full':($sub?->status==='revoked'?'revoked':($sub?'expired':'preview_only'));
  return $this->success(['course'=>CatalogResources::course($course,$r->user()),'subscription'=>CatalogResources::subscription($sub,CatalogResources::courseProgress($course,$r->user())),'access_status'=>$access,'progress_percentage'=>CatalogResources::courseProgress($course,$r->user()),'videos'=>$videos,'files'=>$files]);
 }
 public function playbackUrl(Request $r,CourseVideo $video){
  $d=$r->validate(['quality'=>['nullable',Rule::in(['auto','hd','sd'])],'device_id'=>['nullable','string'],'resume'=>['nullable','boolean']]);
  $this->authorizeVideo($r,$video);abort_unless($video->status==='ready',409,'VIDEO_NOT_READY');
  $path=$video->hls_manifest_path ?: $video->source_path;$url=Storage::disk(config('filesystems.private'))->temporaryUrl($path,now()->addMinutes(config('elder.signed_url_ttl_minutes')));
  $progress=$video->progress()->where('user_id',$r->user()->id)->first();
  return $this->success(['video_id'=>$video->id,'playback_url'=>$url,'format'=>$video->hls_manifest_path?'hls':'mp4','expires_at'=>now()->addMinutes(config('elder.signed_url_ttl_minutes'))->toIso8601String(),'start_position_seconds'=>$r->boolean('resume',true)?($progress?->last_position_seconds??0):0,'duration_seconds'=>$video->duration_seconds,'headers'=>(object)[]],__('api.playback_authorized'),'PLAYBACK_AUTHORIZED');
 }
 public function progress(Request $r,CourseVideo $video){
  $this->authorizeVideo($r,$video);$d=$r->validate(['position_seconds'=>['required','integer','min:0'],'duration_seconds'=>['required','integer','min:1'],'watched_seconds'=>['nullable','integer','min:0'],'completed'=>['nullable','boolean'],'event'=>['nullable',Rule::in(['heartbeat','pause','background','exit','complete'])],'device_id'=>['nullable','string']]);
  $duration=min($video->duration_seconds,max(1,$d['duration_seconds']));$position=min($duration,$d['position_seconds']);$incoming=min($duration,$d['watched_seconds']??$position);
  $p=VideoProgress::firstOrNew(['user_id'=>$r->user()->id,'course_video_id'=>$video->id]);$p->watched_seconds=max($p->watched_seconds??0,$incoming);$p->last_position_seconds=$position;$pct=(int)round(($p->watched_seconds/$duration)*100);$complete=($d['completed']??false)||$pct>=config('elder.video_completion_percentage');if($complete)$p->completed_at??=now();$p->last_watched_at=now();$p->save();
  return $this->success($this->progressData($p,$video,$r),__('api.progress_saved'),'PROGRESS_SAVED');
 }
 public function complete(Request $r,CourseVideo $video){$this->authorizeVideo($r,$video);$p=VideoProgress::updateOrCreate(['user_id'=>$r->user()->id,'course_video_id'=>$video->id],['watched_seconds'=>$video->duration_seconds,'last_position_seconds'=>$video->duration_seconds,'completed_at'=>now(),'last_watched_at'=>now()]);return $this->success($this->progressData($p,$video,$r),__('api.video_completed'),'VIDEO_COMPLETED');}
 public function fileDownloadUrl(Request $r,CourseFile $courseFile){$this->authorizeCourse($r,$courseFile->course);abort_unless($courseFile->is_downloadable,403,'DOWNLOAD_NOT_ALLOWED');$url=Storage::disk(config('filesystems.private'))->temporaryUrl($courseFile->file_path,now()->addMinutes(config('elder.signed_url_ttl_minutes')));return $this->success(['file_id'=>$courseFile->id,'download_url'=>$url,'filename'=>$courseFile->original_name,'mime_type'=>$courseFile->mime_type,'extension'=>$courseFile->extension,'size_bytes'=>$courseFile->size_bytes,'checksum'=>null,'expires_at'=>now()->addMinutes(config('elder.signed_url_ttl_minutes'))->toIso8601String(),'headers'=>(object)[]],__('api.download_authorized'),'DOWNLOAD_AUTHORIZED');}
 public function videoDownloadUrl(Request $r,CourseVideo $video){$this->authorizeVideo($r,$video);abort_unless($video->is_downloadable,403,'DOWNLOAD_NOT_ALLOWED');$url=Storage::disk(config('filesystems.private'))->temporaryUrl($video->source_path,now()->addMinutes(config('elder.signed_url_ttl_minutes')));return $this->success(['video_id'=>$video->id,'download_url'=>$url,'filename'=>'video-'.$video->id.'.mp4','mime_type'=>'video/mp4','size_bytes'=>null,'quality'=>$r->string('quality','hd'),'checksum'=>null,'expires_at'=>now()->addMinutes(config('elder.signed_url_ttl_minutes'))->toIso8601String(),'headers'=>(object)[]],__('api.download_authorized'),'DOWNLOAD_AUTHORIZED');}
 private function authorizeVideo(Request $r,CourseVideo $v){if(!$v->is_preview)$this->authorizeCourse($r,$v->course);}
 private function authorizeCourse(Request $r,Course $c){abort_unless($this->subscription($r,$c)?->isActive(),403,'SUBSCRIPTION_REQUIRED');}
 private function subscription(Request $r,Course $c){return $c->subscriptions()->where('user_id',$r->user()->id)->latest()->first();}
 private function progressData($p,$video,$r){$pct=min(100,(int)round(($p->watched_seconds/max(1,$video->duration_seconds))*100));return ['video_id'=>$video->id,'last_position_seconds'=>$p->last_position_seconds,'watched_seconds'=>$p->watched_seconds,'progress_percentage'=>$pct,'is_completed'=>(bool)$p->completed_at,'completed_at'=>$p->completed_at?->toIso8601String(),'course_progress_percentage'=>CatalogResources::courseProgress($video->course,$r->user()),'next_video_id'=>$video->course->videos()->where('sort_order','>',$video->sort_order)->where('status','ready')->orderBy('sort_order')->value('id')];}
}
