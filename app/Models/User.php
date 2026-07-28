<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordLink;

class User extends Authenticatable implements FilamentUser, CanResetPassword
{
    use HasApiTokens, HasFactory, Notifiable, CanResetPasswordTrait;

    protected $fillable = [
        'name',
        'surname',
        'email',
        'phone',
        'avatar',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active'         => 'boolean',
            'password'          => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Solo admin attivi possono accedere al pannello Filament
        return $this->is_active === true && $this->role === 'admin';
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function productFavorites()
    {
        return $this->belongsToMany(Product::class, 'product_favorites')->withTimestamps();
    }

    public function loyaltyPointTransactions()
    {
        return $this->hasMany(LoyaltyPointTransaction::class);
    }

    public function loyaltyRewards()
    {
        return $this->hasMany(LoyaltyReward::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }
}
