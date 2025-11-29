<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $table = 'vendors';

    public $timestamps = false;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title',
        'author',
        'zoneId',
        'zone_slug',
        'restaurant_slug',
        'phonenumber',
        'description',
        'latitude',
        'longitude',
        'location',
        'photo',
        'photos',
        'restaurantMenuPhotos',
        'categoryID',
        'categoryTitle',
        'cuisineID',
        'cuisineTitle',
        'filters',
        'specialDiscount',
        'specialDiscountEnable',
        'workingHours',
        'DeliveryCharge',
        'adminCommission',
        'adminCommissionType',
        'isOpen',
        'enabledDiveInFuture',
        'openDineTime',
        'closeDineTime',
        'restaurantCost',
        'subscriptionPlanId',
        'subscription_plan',
        'subscriptionExpiryDate',
        'subscriptionTotalOrders',
        'walletAmount',
        'isSelfDelivery',
        'hidephotos',
        'reststatus',
        'dine_in_active',
        'vType',
        'createdAt',
    ];

    protected $casts = [
        'specialDiscountEnable' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'photos' => 'array',
        'restaurantMenuPhotos' => 'array',
        'categoryID' => 'array',
        'categoryTitle' => 'array',
        'filters' => 'array',
        'specialDiscount' => 'array',
        'workingHours' => 'array',
        'DeliveryCharge' => 'array',
        'adminCommission' => 'array',
        'isOpen' => 'boolean',
        'enabledDiveInFuture' => 'boolean',
        'subscription_plan' => 'array',
        'walletAmount' => 'float',
    ];
}

