<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Models\SupportRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class SupportController extends Controller {
 use ApiResponse;
 public function page(string $slug){abort_unless(in_array($slug,['privacy-policy','terms','help']),404);$p=ContentPage::where('slug',$slug)->where('is_active',true)->firstOrFail();return $this->success(['slug'=>$p->slug,'title'=>$p->translated('title'),'content'=>$p->translated('content'),'updated_at'=>$p->updated_at->toIso8601String()]);}
 public function store(Request $r){$d=$r->validate(['subject'=>['required','string','min:3','max:150'],'message'=>['required','string','min:10','max:5000'],'category'=>['nullable',Rule::in(['technical','subscription','content','account','other'])],'attachment'=>['nullable','file','mimes:jpg,jpeg,png,pdf,doc,docx','max:10240'],'device_info'=>['nullable','array']]);$path=isset($d['attachment'])?$d['attachment']->store('support','private'):null;$s=SupportRequest::create(['user_id'=>$r->user()->id,'reference'=>'SUP-'.strtoupper(Str::random(10)),'subject'=>$d['subject'],'message'=>$d['message'],'category'=>$d['category']??'other','attachment_path'=>$path,'device_info'=>$d['device_info']??null,'status'=>'open']);return $this->success(['id'=>$s->id,'reference'=>$s->reference,'status'=>$s->status,'created_at'=>$s->created_at->toIso8601String()],__('api.support_created'),'SUPPORT_REQUEST_CREATED',201);}
}
