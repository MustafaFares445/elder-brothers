<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ContentPage extends Model {
 protected $fillable=['slug','title','content','is_active'];
 protected function casts(): array { return ['title'=>'array','content'=>'array','is_active'=>'boolean']; }
 public function translated(string $field): ?string { $v=$this->{$field}; return $v[app()->getLocale()] ?? $v[config('app.fallback_locale')] ?? null; }
}
