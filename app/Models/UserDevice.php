<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class UserDevice extends Model {
 protected $fillable=['user_id','device_id','fcm_token','platform','app_version','notifications_enabled','last_seen_at'];
 protected function casts(): array { return ['notifications_enabled'=>'boolean','last_seen_at'=>'datetime']; }
 public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
