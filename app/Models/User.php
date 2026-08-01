<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'full_name',
        'phone',
        'pending_phone',
        'email',
        'password',
        'phone_verified_at',
        'avatar_path',
        'status',
        'account_active',
        'is_admin',
        'last_login_at',
        'suspended_at',
        'suspension_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'account_active',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'phone_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            if ($user->wasChanged('status') && $user->status !== 'active') {
                $user->tokens()->delete();
            }
        });
    }

    protected function accountActive(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->status === 'active',
            set: fn (bool $active): array => ! $active && $this->status === 'suspended'
                ? []
                : [
                    'status' => $active ? 'active' : 'inactive',
                    'suspended_at' => null,
                    'suspension_reason' => null,
                ],
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->is_admin
            && $this->status === 'active';
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CourseSubscription::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function videoProgress(): HasMany
    {
        return $this->hasMany(VideoProgress::class);
    }
}
