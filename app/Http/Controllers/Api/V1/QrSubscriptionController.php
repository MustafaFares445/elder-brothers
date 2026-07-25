<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResources;
use App\Models\CourseSubscription;
use App\Models\QrRedemption;
use App\Models\SubscriptionQrCode;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrSubscriptionController extends Controller {
 use ApiResponse;
 public function preview(Request $r){
  $d=$r->validate(['code'=>['required','string','max:2048']]);$qr=$this->find($d['code']);
  if(!$qr)return $this->error('QR_INVALID',__('api.qr_invalid'),422);if($e=$this->stateError($qr))return $e;
  $existing=$r->user()->subscriptions()->where('course_id',$qr->course_id)->latest()->first();
  $expires=$qr->subscription_duration_days?now()->addDays($qr->subscription_duration_days):null;
  return $this->success(['valid'=>true,'course'=>CatalogResources::course($qr->course,$r->user()),'subscription_duration_days'=>$qr->subscription_duration_days,'code_expires_at'=>$qr->expires_at?->toIso8601String(),'subscription_expires_at'=>$expires?->toIso8601String(),'already_subscribed'=>$existing?->isActive()??false,'existing_subscription'=>CatalogResources::subscription($existing),'redemption_policy'=>$existing?->isActive()?'extend':'create','confirmation_required'=>true],__('api.qr_valid'),'QR_VALID');
 }
 public function redeem(Request $r){
  $d=$r->validate(['code'=>['required','string','max:2048'],'device_id'=>['nullable','string','max:191'],'confirm'=>['accepted']]);
  return DB::transaction(function()use($r,$d){
   $qr=SubscriptionQrCode::where('code_hash',hash('sha256',$d['code']))->lockForUpdate()->first();
   if(!$qr)return $this->error('QR_INVALID',__('api.qr_invalid'),422);if($e=$this->stateError($qr))return $e;
   if(QrRedemption::where('subscription_qr_code_id',$qr->id)->where('user_id',$r->user()->id)->exists())return $this->error('QR_ALREADY_USED',__('api.qr_already_used'),409);
   $existing=$r->user()->subscriptions()->where('course_id',$qr->course_id)->latest()->first();$old=$existing?->expires_at;
   $base=$existing?->isActive()&&$existing->expires_at?->isFuture()?$existing->expires_at:now();$new=$qr->subscription_duration_days?$base->copy()->addDays($qr->subscription_duration_days):null;
   $sub=CourseSubscription::updateOrCreate(['user_id'=>$r->user()->id,'course_id'=>$qr->course_id],['source'=>'qr','starts_at'=>$existing?->starts_at??now(),'expires_at'=>$new,'revoked_at'=>null,'status'=>'active']);
   $red=QrRedemption::create(['subscription_qr_code_id'=>$qr->id,'user_id'=>$r->user()->id,'course_subscription_id'=>$sub->id,'redeemed_at'=>now(),'ip_address'=>$r->ip(),'device_id'=>$d['device_id']??null]);$qr->increment('redemptions_count');
   $r->user()->notify(new \App\Notifications\SubscriptionActivatedNotification($sub));
   return $this->success(['subscription'=>CatalogResources::subscription($sub,0),'course'=>CatalogResources::course($qr->course,$r->user()),'redemption_id'=>$red->id,'was_extended'=>(bool)$existing,'previous_expires_at'=>$old?->toIso8601String(),'new_expires_at'=>$new?->toIso8601String()],__('api.subscription_activated'),'SUBSCRIPTION_ACTIVATED',$existing?200:201);
  });
 }
 private function find(string $raw){return SubscriptionQrCode::with('course')->where('code_hash',hash('sha256',$raw))->first();}
 private function stateError($qr){if($qr->status==='disabled')return $this->error('QR_DISABLED',__('api.qr_disabled'),422);if($qr->expires_at?->isPast())return $this->error('QR_EXPIRED',__('api.qr_expired'),422);if($qr->starts_at?->isFuture())return $this->error('QR_NOT_STARTED',__('api.qr_not_started'),422);if($qr->max_redemptions!==null&&$qr->redemptions_count >= $qr->max_redemptions)return $this->error('QR_LIMIT_REACHED',__('api.qr_limit_reached'),409);return null;}
}
