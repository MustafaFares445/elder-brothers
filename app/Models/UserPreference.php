<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserPreference extends Model {
 protected $fillable=['user_id','locale','smart_notifications','download_quality'];
 protected function casts(): array { return ['smart_notifications'=>'boolean']; }
}
