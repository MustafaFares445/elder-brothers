<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
class NotificationController extends Controller {
 use ApiResponse;
 public function index(Request $r){$q=$r->user()->notifications();if($r->string('status')==='unread')$q->whereNull('read_at');if($r->string('status')==='read')$q->whereNotNull('read_at');$p=$q->paginate(min(50,$r->integer('per_page',15)));$p->setCollection($p->getCollection()->map(fn($n)=>$this->resource($n)));return $this->success($p);}
 public function unreadCount(Request $r){return $this->success(['count'=>$r->user()->unreadNotifications()->count()]);}
 public function read(Request $r,string $notification){$n=$r->user()->notifications()->findOrFail($notification);$n->markAsRead();return $this->success($this->resource($n->fresh()),__('api.notification_read'),'NOTIFICATION_READ');}
 public function readAll(Request $r){$count=$r->user()->unreadNotifications()->count();$r->user()->unreadNotifications->markAsRead();return $this->success(['updated_count'=>$count],__('api.notifications_read'),'NOTIFICATIONS_READ');}
 private function resource($n){$d=$n->data;return ['id'=>$n->id,'type'=>$d['type']??class_basename($n->type),'title'=>$d['title_'.app()->getLocale()]??$d['title']??'','body'=>$d['body_'.app()->getLocale()]??$d['body']??'','image_url'=>$d['image_url']??null,'action_type'=>$d['action_type']??null,'action_id'=>$d['action_id']??null,'action_url'=>$d['action_url']??null,'is_read'=>(bool)$n->read_at,'read_at'=>$n->read_at?->toIso8601String(),'created_at'=>$n->created_at->toIso8601String()];}
}
