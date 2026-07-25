<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Subject extends Model {
 protected $fillable=['academic_year_id','title','image_path','sort_order','is_active'];
 protected function casts(): array { return ['title'=>'array','is_active'=>'boolean']; }
 public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
 public function courses(): HasMany { return $this->hasMany(Course::class); }
 public function translated(string $field): ?string { $v=$this->{$field}; return $v[app()->getLocale()] ?? $v[config('app.fallback_locale')] ?? null; }
}
