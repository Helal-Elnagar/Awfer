<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGift extends Model
{
    protected $table = 'user_gift';

    protected $fillable = [
        'user_id',
        'gift_id',
        'coupon_id',
        'expires_at',
        'is_used',
        'is_finished'

    ];

        protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function gift()
    {
        return $this->belongsTo(Gift::class);
    }

    
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
    
}
