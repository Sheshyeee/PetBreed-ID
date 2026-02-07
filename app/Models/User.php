<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens; // Added for Sanctum
use Illuminate\Notifications\Notifiable; // Re-added for standard functionality

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function scans()
    {
        return $this->hasMany(Results::class);
    }

    public function verifiedScans()
    {
        return $this->hasMany(Results::class)->where('pending', 'verified');
    }

    public function pendingScans()
    {
        return $this->hasMany(Results::class)->where('pending', 'pending');
    }

    /**
     * Get all custom notifications for this user
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customNotifications()
    {
        return $this->hasMany(\App\Models\Notification::class, 'user_id');
    }

    /**
     * Get unread custom notifications
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function unreadCustomNotifications()
    {
        return $this->hasMany(\App\Models\Notification::class, 'user_id')
            ->where('read', false)
            ->orWhereNull('read');
    }

    /**
     * Get unread notification count
     */
    public function getUnreadNotificationCountAttribute()
    {
        return $this->customNotifications()->where('read', false)->count();
}
}
