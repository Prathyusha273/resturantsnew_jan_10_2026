<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterProduct extends Model
{
    protected $table = 'master_products';

    public $timestamps = false;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'publish' => 'boolean',
        'nonveg' => 'boolean',
        'veg' => 'boolean',
        'suggested_price' => 'float',
        'min_price' => 'float',
        'dis_price' => 'float',
        'photos' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(VendorCategory::class, 'categoryID');
    }
}

