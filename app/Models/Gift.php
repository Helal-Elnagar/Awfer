<?php

namespace App\Models;

use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Gift extends BaseModel
{
    use HasTranslations;

    protected $fillable = ['name', 'min_points', 'coupon_id', 'level_id'];

    public function codes()
    {
        return $this->hasMany(GiftCode::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_gift')
            ->withPivot(['coupon_id', 'expires_at'])
            ->withTimestamps();
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function level()
    {
        return $this->belongsTo(LevelGift::class, 'level_id');
    }

    public function getExpiresAtAttribute($value)
    {
        if ($value) {
            return $value;
        }

        return null;
    }
}
