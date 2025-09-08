<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'gateway_data',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'gateway_data' => 'array',
        ];
    }

    /**
     * Set the user's name with XSS protection.
     */
    protected function setNameAttribute($value)
    {
        $this->attributes['name'] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }


// Add to User class:
public function socialConnections(): HasMany
{
    return $this->hasMany(SocialConnection::class);
}

/**
 * Get the orders associated with the user.
 */
public function orders(): HasMany
{
    return $this->hasMany(Order::class);
}

/**
 * Get the subscriptions associated with the user.
 */
public function subscriptions(): HasMany
{
    return $this->hasMany(Subscription::class);
}

/**
 * Get the transactions associated with the user.
 */
public function transactions(): HasMany
{
    return $this->hasMany(Transaction::class);
}
}