<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OtpCode extends Model {
 protected $fillable=['phone','purpose','code_hash','expires_at','verified_at','attempts','resend_available_at'];
 protected function casts(): array { return ['expires_at'=>'datetime','verified_at'=>'datetime','resend_available_at'=>'datetime']; }
}
