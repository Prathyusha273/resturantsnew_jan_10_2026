<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'firstName',
        'lastName',
        'email',
        'password',
        'firebase_id',
        '_id',
        'vendorID',
        'wallet_amount',
        'subscriptionPlanId',
        'subscription_plan',
        'subscriptionExpiryDate',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_backup_codes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
        'wallet_amount' => 'float',
        'shippingAddress' => 'array',
        'subscription_plan' => 'array',
        'userBankDetails' => 'array',
    ];

    /**
     * Use firebase_id as the auth identifier so Auth::id() returns it.
     */
    public function getAuthIdentifierName()
    {
        return 'firebase_id';
    }

    public function getvendorId()
    {
        return $this->vendorID ?? null;
    }

    public function getNameAttribute()
    {
        $name = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));

        return $name !== '' ? $name : ($this->email ?? '');
    }
}
