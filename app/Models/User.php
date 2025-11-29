<?php

namespace App\Models;

use Illuminate\Auth\Notifications\ResetPassword;
use App\Notifications\CustomResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    /** We store firebase_id as auth key */
//    protected $primaryKey = 'firebase_id';
//    public $incrementing = false;
//    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'firebase_id',
//        '_id',
        'firstName',
        'lastName',
        'email',
        'password',
        'countryCode',
        'phoneNumber',
        'provider',
        'appIdentifier',
        'role',
        'createdAt',
        'active',
        'isDocumentVerify',
        'wallet_amount',
        'vType',
        'profilePictureURL',
        'vendorID',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
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

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }

}
