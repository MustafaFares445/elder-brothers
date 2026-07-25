<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CourseVideo extends Model {
 protected $fillable=['course_id','title','lesson_label','thumbnail_path','source_path','hls_manifest_path','duration_seconds','sort_order','is_preview','is_downloadable','status'];
 protected function casts(): array { return ['title'=>'array','lesson_label'=>'array','is_preview'=>'boolean','is_downloadable'=>'boolean']; }
 public function course(): BelongsTo { return $this->belongsTo(Course::class); }
 public function progress(): HasMany { return $this->hasMany(VideoProgress::class); }
 public function translated(string $field): ?string { $v=$this->{$field}; return $v[app()->getLocale()] ?? $v[config('app.fallback_locale')] ?? null; }
}
