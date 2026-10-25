<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'address_name',
        'city_id',
        'building_number',
        'floor_number',
        'address_type',
        'districts_id',
        'district_name',
        'latitude',
        'longitude',
        'phone',
        'country_code',
        'is_default',
        'description',
        'note'
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

        public function district()
    {
        return $this->belongsTo(\App\Models\District::class, 'districts_id');
    }

    public function city()
    {
        return $this->belongsTo(\App\Models\City::class, 'city_id');
    }

    public function orders()
    {
        return $this->hasMany(\App\Models\Order::class);
    }

    public function hasOrders()
    {
        return $this->orders()->exists();
    }
}
