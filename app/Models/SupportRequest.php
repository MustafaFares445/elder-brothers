<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupportRequest extends Model {
 protected $fillable=['user_id','reference','subject','message','category','attachment_path','device_info','status'];
 protected function casts(): array { return ['device_info'=>'array']; }
}
