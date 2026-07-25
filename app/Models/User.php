<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['full_name','phone','email','password','phone_verified_at','avatar_path','status','last_login_at'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['password'=>'hashed','phone_verified_at'=>'datetime','last_login_at'=>'datetime']; }
    public function subscriptions(): HasMany { return $this->hasMany(CourseSubscription::class); }
    public function devices(): HasMany { return $this->hasMany(UserDevice::class); }
    public function preferences() { return $this->hasOne(UserPreference::class); }
    public function videoProgress(): HasMany { return $this->hasMany(VideoProgress::class); }
}
