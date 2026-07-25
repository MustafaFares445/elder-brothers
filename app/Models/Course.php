<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Course extends Model {
 protected $fillable=['subject_id','title','description','thumbnail_path','hero_path','status','is_featured','published_at'];
 protected function casts(): array { return ['title'=>'array','description'=>'array','is_featured'=>'boolean','published_at'=>'datetime']; }
 public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
 public function videos(): HasMany { return $this->hasMany(CourseVideo::class); }
 public function files(): HasMany { return $this->hasMany(CourseFile::class); }
 public function subscriptions(): HasMany { return $this->hasMany(CourseSubscription::class); }
 public function translated(string $field): ?string { $v=$this->{$field}; return $v[app()->getLocale()] ?? $v[config('app.fallback_locale')] ?? null; }
}
