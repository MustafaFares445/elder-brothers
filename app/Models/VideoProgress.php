<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class VideoProgress extends Model {
 protected $fillable=['user_id','course_video_id','watched_seconds','last_position_seconds','completed_at','last_watched_at'];
 protected function casts(): array { return ['completed_at'=>'datetime','last_watched_at'=>'datetime']; }
 public function user(): BelongsTo { return $this->belongsTo(User::class); }
 public function video(): BelongsTo { return $this->belongsTo(CourseVideo::class,'course_video_id'); }
}
