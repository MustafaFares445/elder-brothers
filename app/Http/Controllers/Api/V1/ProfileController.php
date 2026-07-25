<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\UserDevice;
use App\Models\UserPreference;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller {
 use ApiResponse;
 public function show(Request $request){
  $u=$request->user();$p=$u->preferences()->firstOrCreate([],['locale'=>'ar','smart_notifications'=>true,'download_quality'=>'auto']);
  return $this->success(['user'=>new UserResource($u),'preferences'=>$p->only(['locale','smart_notifications','download_quality']),'active_subscription_count'=>$u->subscriptions()->where('status','active')->count(),'expired_subscription_count'=>$u->subscriptions()->where('status','expired')->count()]);
 }
 public function update(Request $request){
  $d=$request->validate(['full_name'=>['sometimes','string','min:2','max:100'],'email'=>['nullable','email','max:191',Rule::unique('users','email')->ignore($request->user()->id)],'phone'=>['sometimes','string','max:30']]);
  $pending=false;$pendingPhone=null;
  if(isset($d['phone'])&&$d['phone']!==$request->user()->phone){$pending=true;$pendingPhone=$d['phone'];unset($d['phone']);}
  $request->user()->update($d);
  return $this->success(['user'=>new UserResource($request->user()->fresh()),'phone_verification_required'=>$pending,'pending_phone'=>$pendingPhone],__('api.profile_updated'),'PROFILE_UPDATED');
 }
 public function avatar(Request $request){
  $d=$request->validate(['avatar'=>['required','image','mimes:jpg,jpeg,png,webp','max:5120']]);
  $path=$d['avatar']->store('avatars','public');$request->user()->update(['avatar_path'=>$path]);
  return $this->success(['avatar_url'=>asset('storage/'.$path),'user'=>new UserResource($request->user()->fresh())],__('api.avatar_updated'),'AVATAR_UPDATED');
 }
 public function password(Request $request){
  $d=$request->validate(['current_password'=>['required','string'],'password'=>['required','string','min:8','confirmed']]);
  if(!Hash::check($d['current_password'],$request->user()->password)) return $this->error('CURRENT_PASSWORD_INCORRECT',__('api.current_password_incorrect'),422);
  $request->user()->update(['password'=>$d['password']]);return $this->success(null,__('api.password_updated'),'PASSWORD_UPDATED');
 }
 public function preferences(Request $request){
  $d=$request->validate(['locale'=>['sometimes',Rule::in(['ar','en'])],'smart_notifications'=>['sometimes','boolean'],'download_quality'=>['sometimes',Rule::in(['auto','hd','sd'])]]);
  $p=UserPreference::updateOrCreate(['user_id'=>$request->user()->id],$d);
  return $this->success($p->only(['locale','smart_notifications','download_quality']),__('api.preferences_updated'),'PREFERENCES_UPDATED');
 }
 public function storeDevice(Request $request){
  $d=$request->validate(['device_id'=>['required','string','max:191'],'fcm_token'=>['required','string','max:2048'],'platform'=>['required',Rule::in(['android','ios'])],'app_version'=>['nullable','string','max:30'],'notifications_enabled'=>['nullable','boolean']]);
  $device=UserDevice::updateOrCreate(['user_id'=>$request->user()->id,'device_id'=>$d['device_id']],$d+['last_seen_at'=>now()]);
  return $this->success($device->only(['id','device_id','platform','notifications_enabled','last_seen_at']),__('api.device_registered'),'DEVICE_REGISTERED');
 }
 public function destroyDevice(Request $request,UserDevice $device){
  abort_unless($device->user_id===$request->user()->id,404);$device->delete();return $this->success(null,__('api.device_removed'),'DEVICE_REMOVED');
 }
}
